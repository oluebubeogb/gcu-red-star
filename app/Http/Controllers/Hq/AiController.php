<?php
namespace App\Http\Controllers\Hq;

use App\Ai\Ai;
use App\Ai\Replicate;
use App\Http\Controllers\Controller;
use App\Jobs\CleanOcrPageJob;
use App\Jobs\GenerateAudioJob;
use App\Jobs\IndexEditionJob;
use App\Jobs\ReviewSubmissionJob;
use App\Jobs\SummarizeEditionJob;
use App\Models\AiSummary;
use App\Models\AskLog;
use App\Models\Edition;
use App\Models\EditionAudio;
use App\Models\EditionChunk;
use App\Models\EditionPage;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use App\Storage\MagazineStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** HQ → AI: status, switches, index rebuild, top questions, and per-edition review (summaries, OCR, audio). */
class AiController extends Controller
{
    public function __construct(private Ai $ai) {}

    private function needs(string $feature): void
    {
        abort_unless($this->ai->available($feature), 422, 'This AI feature is off or REPLICATE_API_TOKEN is not set.');
    }

    public function index()
    {
        return Inertia::render('Hq/Ai', [
            'configured' => $this->ai->configured(),
            'flags' => $this->ai->flags(),
            'switches' => collect(Ai::FEATURES)->mapWithKeys(fn ($f) => [$f => (bool) Setting::get("ai.$f", true)]),
            'env' => config('ai.features'),
            'autoPublish' => $this->ai->autoPublish(),
            'models' => config('ai.models'),
            'pgvector' => Ai::pgvector(),
            'stats' => [
                'chunks' => EditionChunk::count(), 'embedded' => EditionChunk::whereNotNull('embedded_at')->count(),
                'callsToday' => Replicate::callsToday(), 'dailyCap' => config('ai.daily_cap'),
                'pendingSummaries' => AiSummary::where('status', 'draft')->count(),
                'pendingOcr' => EditionPage::where('cleaned_status', 'pending')->count(),
            ],
            'topQuestions' => AskLog::where('created_at', '>=', now()->subDays(90))
                ->select('normalized', DB::raw('count(*) as c'), DB::raw('max(question) as question'),
                    DB::raw('sum(case when answered then 1 else 0 end) as answered'))
                ->groupBy('normalized')->orderByDesc('c')->limit(30)->get(),
        ]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['switches' => ['array'], 'switches.*' => ['boolean'], 'auto_publish' => ['boolean']]);
        foreach ($data['switches'] ?? [] as $f => $on) if (in_array($f, Ai::FEATURES, true)) Setting::put("ai.$f", (bool) $on);
        if (array_key_exists('auto_publish', $data)) Setting::put('ai.auto_publish', (bool) $data['auto_publish']);
        return back()->with('status', 'AI settings saved.');
    }

    public function rebuild()
    {
        abort_unless($this->ai->available('search') || $this->ai->available('ask'), 422, 'Semantic search and Ask are off or REPLICATE_API_TOKEN is not set.');
        $n = 0;
        Edition::where('processing_status', 'ready')->pluck('id')->each(function ($id) use (&$n) { IndexEditionJob::dispatch($id, false); $n++; });
        return back()->with('status', "Queued AI index rebuild for {$n} editions.");
    }

    // ── Per-edition review ────────────────────────────────────────────
    public function edition(Edition $edition, MagazineStorage $storage)
    {
        $names = User::directory()->pluck('username', 'name');
        return Inertia::render('Hq/Editions/Ai', [
            'edition' => $edition->only(['id', 'title', 'slug', 'page_count', 'processing_status']) + ['ai_indexed_at' => optional($edition->ai_indexed_at)->toDateTimeString()],
            'flags' => $this->ai->flags(),
            'summaries' => $edition->summaries()->orderByRaw('page_from is not null')->orderBy('page_from')->get()
                ->map(fn ($s) => $s->only(['id', 'page_from', 'page_to', 'summary', 'entities', 'status']) + [
                    // People who match member names → tag suggestions (never auto-created).
                    'suggestedTags' => collect($s->entities['people'] ?? [])->filter(fn ($p) => isset($names[$p]))
                        ->map(fn ($p) => ['name' => $p, 'username' => $names[$p]])->values(),
                ]),
            'ocrPages' => $edition->pages()->whereNotNull('cleaned_status')->get(['id', 'page_number', 'text', 'cleaned_text', 'cleaned_status', 'ocr']),
            'needsCleanup' => $edition->pages()->get()->filter->needsCleanup()->whereNull('cleaned_status')->count(),
            'audio' => $edition->audio()->get()->map(fn ($a) => $a->only(['page_number', 'status', 'error', 'chars'])
                + ['url' => $a->audio_key ? $storage->url($a->audio_key) : null]),
            'chunks' => ['total' => $edition->chunks()->count(), 'embedded' => $edition->chunks()->whereNotNull('embedded_at')->count()],
        ]);
    }

    public function reindex(Edition $edition)
    {
        IndexEditionJob::dispatch($edition->id);
        return back()->with('status', 'AI index rebuild queued.');
    }

    public function summarize(Edition $edition)
    {
        $this->needs('summaries');
        SummarizeEditionJob::dispatch($edition->id);
        return back()->with('status', 'Summaries are being generated (refresh in a minute).');
    }

    public function updateSummary(Request $request, AiSummary $summary)
    {
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:3000'], 'status' => ['required', 'in:draft,approved,rejected'],
            'entities' => ['array'], 'entities.*' => ['array'], 'entities.*.*' => ['string', 'max:100'],
        ]);
        $summary->update(['summary' => $data['summary'], 'status' => $data['status'], 'reviewed_by' => $request->user()->id,
            'entities' => array_intersect_key($data['entities'] ?? $summary->entities ?? [], array_flip(['people', 'places', 'events', 'years']))]);
        return back()->with('status', 'Summary '.$data['status'].'.');
    }

    public function cleanOcr(Edition $edition)
    {
        $this->needs('ocr');
        $pages = $edition->pages()->get()->filter->needsCleanup()->filter(fn ($p) => $p->cleaned_status !== 'approved');
        $pages->each(fn ($p) => CleanOcrPageJob::dispatch($p->id));
        return back()->with('status', "OCR cleanup queued for {$pages->count()} pages.");
    }

    public function reviewOcr(Request $request, EditionPage $page)
    {
        $data = $request->validate(['cleaned_text' => ['required', 'string', 'max:60000'], 'status' => ['required', 'in:approved,rejected,pending']]);
        $page->forceFill(['cleaned_text' => $data['cleaned_text'], 'cleaned_status' => $data['status']])->save();
        IndexEditionJob::dispatch($page->edition_id, false); // re-chunk with the approved text (unchanged chunks keep embeddings)
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') \App\Support\ArchiveSearch::refreshVocabulary();
        return back()->with('status', 'OCR review saved.');
    }

    public function audio(Request $request, Edition $edition)
    {
        $this->needs('audio');
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $pages = isset($data['page']) ? collect([$data['page']]) : $edition->pages()->pluck('page_number');
        foreach ($pages as $n) {
            $a = EditionAudio::firstOrCreate(['edition_id' => $edition->id, 'page_number' => $n]);
            if ($a->status === 'ready' && ! ($data['page'] ?? null)) continue; // don't pay twice
            $a->update(['status' => 'pending', 'error' => null]);
            GenerateAudioJob::dispatch($a->id);
        }
        return back()->with('status', 'Audio generation queued for '.$pages->count().' page(s).');
    }

    public function reviewSubmission(Submission $submission)
    {
        $this->needs('assistant');
        ReviewSubmissionJob::dispatch($submission->id);
        return back()->with('status', 'Assistant review queued.');
    }
}
