<?php

namespace Tests\Feature;

use App\Ai\AskArchive;
use App\Jobs\IndexEditionJob;
use App\Jobs\SummarizeEditionJob;
use App\Models\AiSummary;
use App\Models\AskLog;
use App\Models\Edition;
use App\Models\EditionChunk;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase5Test extends TestCase
{
    use RefreshDatabase;

    /** Replies the fake LLM gives, matched by a substring of the prompt. */
    private array $llm = [];
    private bool $down = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Fallback path by default; run on Postgres with AI_TEST_PGVECTOR=1 REDSTAR_AI_EMBED_DIMS=8 to exercise pgvector.
        config(['ai.token' => 'r8_test', 'ai.pgvector' => (bool) env('AI_TEST_PGVECTOR', false)]);
        Http::preventStrayRequests();
        Http::fake(['api.replicate.com/*' => function (Request $r) {
            if ($this->down) return Http::response(['detail' => 'boom'], 500);
            $in = $r->data()['input'] ?? [];
            if (str_contains($r->url(), 'granite-embedding')) {
                // Deterministic toy embeddings: word-presence vector over a tiny vocabulary.
                $vocab = ['football', 'cup', 'principal', 'chapel', 'science', 'kingsley', 'sports', 'won'];
                $out = array_map(fn ($t) => array_map(fn ($w) => str_contains(strtolower($t), $w) ? 1.0 : 0.0, $vocab), $in['texts']);
                return Http::response(['id' => 'e1', 'status' => 'succeeded', 'output' => $out]);
            }
            foreach ($this->llm as $needle => $reply) {
                if (str_contains($in['prompt'] ?? '', $needle)) return Http::response(['id' => 'p1', 'status' => 'succeeded', 'output' => [$reply]]);
            }
            return Http::response(['id' => 'p0', 'status' => 'succeeded', 'output' => ['NOT_FOUND']]);
        }]);
    }

    private function edition(string $slug, string $access, array $pages): Edition
    {
        $e = Edition::create(['title' => ucfirst($slug), 'slug' => $slug, 'year' => 1976, 'access' => $access, 'status' => 'published',
            'published_at' => now()->subDay(), 'processing_status' => 'ready', 'processed_at' => now()->subHour(), 'page_count' => count($pages)]);
        foreach ($pages as $i => $text) $e->pages()->create(['page_number' => $i + 1, 'text' => $text]);
        return $e;
    }

    public function test_missing_token_degrades_gracefully(): void
    {
        config(['ai.token' => null]);
        $this->edition('harmattan', 'public', ['The football team won the cup.']);
        $this->get('/ask')->assertOk()->assertInertia(fn (Assert $p) => $p->where('available', false)->where('ai.ask', false)->where('ai.search', false));
        $this->postJson('/ask', ['question' => 'Who won the cup?'])->assertStatus(503);
        $this->get('/search?q=football')->assertOk()->assertInertia(fn (Assert $p) => $p->where('semantic', false)->where('results.total', 1));
        $this->get('/editions/harmattan')->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary', null));
        (new IndexEditionJob(Edition::first()->id))->handle(app(\App\Ai\Ai::class), app(\App\Ai\SemanticIndex::class));
        $this->assertSame(0, EditionChunk::whereNotNull('embedded_at')->count());
        $this->artisan('redstar:ai-sweep')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_embedding_job_chunks_and_embeds_and_reuses_unchanged_chunks(): void
    {
        config(['ai.features.summaries' => false, 'ai.features.ocr' => false]);
        $e = $this->edition('harmattan', 'public', ['The football team won the cup.', 'The principal opened the new science laboratory.']);
        IndexEditionJob::dispatch($e->id);
        $this->assertSame(2, EditionChunk::whereNotNull('embedded_at')->count());
        $this->assertNotNull($e->fresh()->ai_indexed_at);
        $this->assertCount(8, json_decode(EditionChunk::first()->embedding_json, true));

        $sent = count(Http::recorded());
        IndexEditionJob::dispatch($e->id); // nothing changed → no new embedding calls
        $this->assertSame($sent, count(Http::recorded()));

        $this->get('/search?q=football')->assertInertia(fn (Assert $p) => $p->where('semantic', true)->where('results.data.0.page', 1));
    }

    public function test_ask_cites_pages_and_respects_visibility_and_refuses_without_context(): void
    {
        config(['ai.features.summaries' => false, 'ai.features.ocr' => false]);
        $pub = $this->edition('harmattan', 'public', ['Kingsley scored twice as the football team won the cup.']);
        $mem = $this->edition('members-only', 'members', ['The chapel choir sang at the principal\'s farewell.']);
        IndexEditionJob::dispatch($pub->id);
        IndexEditionJob::dispatch($mem->id);
        // Fake LLM: answers only when the prompt carries the right question/source.
        $this->llm = ['Question: Who scored' => 'Kingsley scored twice in the cup win [1].', 'choir sang at' => 'The chapel choir sang at the farewell [1].'];

        $r = $this->postJson('/ask', ['question' => 'Who scored when the football team won the cup?'])->assertOk()->json();
        $this->assertTrue($r['found']);
        $this->assertSame('Harmattan', $r['citations'][0]['title']);
        $this->assertSame(1, $r['citations'][0]['page']);
        $this->assertStringContainsString('/editions/harmattan?page=1', $r['citations'][0]['url']);

        // Identical question (normalised) is served from cache.
        $this->postJson('/ask', ['question' => 'who scored when the football team WON the cup'])->assertJson(['cached' => true]);

        // Guests never see members-only editions in sources.
        $g = $this->postJson('/ask', ['question' => 'What did the chapel choir sing at the farewell?'])->json();
        $this->assertFalse($g['found']);
        $this->assertSame(AskArchive::NOT_FOUND, $g['answer']);
        foreach (Http::recorded() as [$req]) $this->assertStringNotContainsString('chapel choir sang', $req->data()['input']['prompt'] ?? '');

        // Verified members do.
        $m = $this->actingAs(User::factory()->create(['username' => 'm1']))
            ->postJson('/ask', ['question' => 'What did the chapel choir sing at the farewell?'])->json();
        $this->assertTrue($m['found']);
        $this->assertSame('Members-only', $m['citations'][0]['title']);

        // No matching context → refusal without calling the LLM.
        $before = count(Http::recorded());
        $n = $this->postJson('/ask', ['question' => 'zzqx blorpt wibble'])->json();
        $this->assertFalse($n['found']);
        $this->assertSame(AskArchive::NOT_FOUND, $n['answer']);
        $llmCalls = Http::recorded()->slice($before)->filter(fn ($p) => str_contains($p[0]->url(), 'llama'))->count();
        $this->assertSame(0, $llmCalls);

        // Every question is logged for HQ's top-questions view.
        $this->assertSame(5, AskLog::count());
        $this->actingAs(User::factory()->create(['username' => 'boss', 'role' => 'admin']))
            ->get('/hq/ai')->assertInertia(fn (Assert $p) => $p->where('topQuestions.0.c', 2));
    }

    public function test_answer_without_valid_citation_is_refused(): void
    {
        config(['ai.features.summaries' => false, 'ai.features.ocr' => false]);
        IndexEditionJob::dispatch($this->edition('harmattan', 'public', ['The football team won the cup.'])->id);
        $this->llm = ['football team won' => 'They won it, I believe.'];
        $this->postJson('/ask', ['question' => 'Did the football team win?'])->assertJson(['found' => false, 'answer' => AskArchive::NOT_FOUND]);
    }

    public function test_summary_review_flow(): void
    {
        $e = $this->edition('harmattan', 'public', ['Kingsley Ude captained the football team in Enugu in 1976.']);
        User::factory()->create(['username' => 'kude', 'name' => 'Kingsley Ude', 'show_in_directory' => true]);
        $this->llm = ['Kingsley Ude' => '{"summary":"Sports report on the football team.","people":["Kingsley Ude"],"places":["Enugu"],"events":["Football"],"years":["1976"]}'];
        SummarizeEditionJob::dispatch($e->id);
        $this->assertSame(0, AiSummary::where('status', '!=', 'draft')->count());
        $this->get('/editions/harmattan')->assertInertia(fn (Assert $p) => $p->where('summary', null)); // drafts are hidden

        $admin = User::factory()->create(['username' => 'boss', 'role' => 'admin']);
        $this->actingAs($admin)->get("/hq/editions/{$e->id}/ai")->assertInertia(fn (Assert $p) => $p
            ->where('summaries.0.status', 'draft')->where('summaries.0.suggestedTags.0.username', 'kude'));
        $this->assertSame(0, \App\Models\PageTag::count()); // suggestions only

        $s = AiSummary::whereNull('page_from')->first();
        $this->actingAs($admin)->put("/hq/ai/summaries/{$s->id}", ['summary' => 'Edited summary.', 'status' => 'approved',
            'entities' => ['people' => ['Kingsley Ude'], 'places' => ['Enugu']]])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/editions/harmattan')->assertInertia(fn (Assert $p) => $p->where('summary.overall', 'Edited summary.')
            ->where('summary.entities.places.0', 'Enugu'));

        // Auto-publish toggle skips review.
        \App\Models\Setting::put('ai.auto_publish', true);
        SummarizeEditionJob::dispatch($e->id);
        $this->assertSame(0, AiSummary::where('status', 'draft')->count());
    }

    public function test_submission_assistant_suggests_without_changing(): void
    {
        $this->llm = ['Submission' => '{"spam_score": 1.7, "flags": ["advertising"], "headline": "Buy cheap phones", "suggestions": ["Remove the link"]}'];
        $u = User::factory()->create(['username' => 'w1']);
        $this->actingAs($u)->post('/submissions', ['kind' => 'article', 'title' => 'hello', 'body' => 'Buy phones now at www.example.com'])->assertSessionHasNoErrors();
        $s = Submission::first();
        $this->assertSame(1.0, (float) $s->ai_review['spam_score']); // clamped
        $this->assertSame('Buy cheap phones', $s->ai_review['headline']);
        $this->assertSame('hello', $s->title); // never auto-applied
        $this->assertSame('submitted', $s->status);

        $admin = User::factory()->create(['username' => 'boss', 'role' => 'admin']);
        $this->actingAs($admin)->get('/hq')->assertInertia(fn (Assert $p) => $p->where('submissions.0.ai_review.flags.0', 'advertising'));
        $this->actingAs($admin)->post("/hq/submissions/{$s->id}/assistant")->assertRedirect();
        $this->actingAs($u)->post("/hq/submissions/{$s->id}/assistant")->assertForbidden();
    }

    public function test_replicate_errors_degrade_and_hq_toggle_disables(): void
    {
        config(['ai.features.summaries' => false, 'ai.features.ocr' => false]);
        IndexEditionJob::dispatch($this->edition('harmattan', 'public', ['The football team won the cup.'])->id);
        $this->down = true;
        $this->postJson('/ask', ['question' => 'Who won the cup?'])->assertStatus(503)->assertJsonStructure(['message']);
        $this->get('/search?q=football')->assertOk();

        \App\Models\Setting::put('ai.ask', false);
        $this->get('/ask')->assertInertia(fn (Assert $p) => $p->where('available', false));
    }
}
