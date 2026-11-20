<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('magazines');
    }

    private function editor(): User { return User::factory()->create(['username' => 'ed', 'role' => 'editor']); }

    private function edition(array $attrs = []): Edition
    {
        $e = Edition::create($attrs + [
            'title' => 'Harmattan 1987', 'slug' => 'harmattan-1987', 'year' => 1987, 'access' => 'public',
            'status' => 'published', 'published_at' => now()->subDay(), 'processing_status' => 'ready',
            'pdf_path' => 'editions/1/original.pdf', 'page_count' => 2,
        ]);
        Storage::disk('magazines')->put($e->pdf_path, '%PDF-1.4');
        $e->pages()->create(['page_number' => 1, 'text' => 'Welcome to Umuahia', 'image_key' => 'editions/1/pages/1.webp']);
        $e->pages()->create(['page_number' => 2, 'text' => 'The Elephant house won the inter-house sports', 'image_key' => 'editions/1/pages/2.webp']);
        $e->blocks()->create(['page_number' => 2, 'position' => 0, 'type' => 'heading', 'text' => 'Sports day']);
        return $e;
    }

    public function test_editor_creates_edition_with_pdf_and_publish_waits_for_processing(): void
    {
        $ed = $this->editor();
        $this->actingAs(User::factory()->create(['username' => 'plain']))->get('/hq/editions')->assertForbidden();

        $this->actingAs($ed)->post('/hq/editions', [
            'title' => 'Speech Day', 'year' => 1990, 'access' => 'members',
            'pdf' => UploadedFile::fake()->create('mag.pdf', 500, 'application/pdf'),
            'cover' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertRedirect();

        $e = Edition::firstWhere('title', 'Speech Day');
        $this->assertSame('1990-speech-day', $e->slug);
        $this->assertSame('draft', $e->status);
        $this->assertSame('pending', $e->processing_status);
        Storage::disk('magazines')->assertExists([$e->pdf_path, $e->cover_path]);
        $this->assertSame("editions/{$e->id}/original.pdf", $e->pdf_path);

        $this->actingAs($ed)->post("/hq/editions/{$e->id}/publish", ['publish' => 1])->assertSessionHasErrors('publish');
        $this->assertSame('draft', $e->fresh()->status);

        $e->update(['processing_status' => 'ready']);
        $this->actingAs($ed)->post("/hq/editions/{$e->id}/publish", ['publish' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($e->fresh()->isPublished());

        $this->actingAs($ed)->put("/hq/editions/{$e->id}", ['title' => 'Speech Day 1990', 'access' => 'public'])->assertSessionHasNoErrors();
        $this->assertSame('Speech Day 1990', $e->fresh()->title);

        $this->actingAs($ed)->post("/hq/editions/{$e->id}/publish", ['publish' => 0]);
        $this->assertSame('draft', $e->fresh()->status);
        $this->actingAs($ed)->delete("/hq/editions/{$e->id}")->assertRedirect('/hq/editions');
        $this->assertDatabaseMissing('editions', ['id' => $e->id]);
    }

    public function test_reader_respects_status_and_access(): void
    {
        $e = $this->edition();
        $this->get('/editions/harmattan-1987?page=2')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Public/Edition')->where('canRead', true)->where('canDownload', false)->where('startPage', 2)
            ->has('pages', 2)->where('blocks.2.0.text', 'Sports day'));

        $e->update(['access' => 'members']);
        $this->get('/editions/harmattan-1987')->assertInertia(fn (Assert $p) => $p->where('canRead', false)->has('pages', 0));
        $this->actingAs(User::factory()->unverified()->create(['username' => 'u0']))->get('/editions/harmattan-1987')
            ->assertInertia(fn (Assert $p) => $p->where('canRead', false));
        $this->actingAs(User::factory()->create(['username' => 'u1']))->get('/editions/harmattan-1987')
            ->assertInertia(fn (Assert $p) => $p->where('canRead', true)->where('canDownload', true));

        $e->update(['status' => 'draft']);
        $this->get('/editions/harmattan-1987')->assertNotFound();
    }

    public function test_search_only_returns_published_pages(): void
    {
        $e = $this->edition();
        $this->get('/search?q=elephant')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Public/Search')->where('results.total', 1)->where('results.data.0.page', 2));
        $e->update(['status' => 'draft']);
        $this->get('/search?q=elephant')->assertInertia(fn (Assert $p) => $p->where('results.total', 0));
    }

    public function test_bookmarks_and_downloads_for_verified_members(): void
    {
        $e = $this->edition();
        $this->get('/editions/harmattan-1987/download')->assertRedirect('/login');

        $u = User::factory()->create(['username' => 'reader']);
        $res = $this->actingAs($u)->get('/editions/harmattan-1987/download');
        $res->assertRedirect();
        $this->assertStringContainsString('/files/editions/1/original.pdf', $res->headers->get('Location'));
        $this->get($res->headers->get('Location'))->assertOk();

        $this->actingAs($u)->post('/bookmarks', ['edition_id' => $e->id, 'page_number' => 2])->assertRedirect();
        $this->actingAs($u)->post('/bookmarks', ['edition_id' => $e->id, 'page_number' => 2]);
        $this->assertSame(1, $u->bookmarks()->count());
        $this->actingAs($u)->get('/bookmarks')->assertInertia(fn (Assert $p) => $p->has('bookmarks', 1));

        $other = User::factory()->create(['username' => 'other']);
        $b = $u->bookmarks()->first();
        $this->actingAs($other)->delete("/bookmarks/{$b->id}")->assertForbidden();
        $this->actingAs($u)->delete("/bookmarks/{$b->id}")->assertRedirect();
        $this->assertSame(0, $u->bookmarks()->count());
    }

    public function test_photo_submissions_require_and_store_a_photo(): void
    {
        $u = User::factory()->create(['username' => 'snap']);
        $this->actingAs($u)->post('/submissions', ['title' => 'Assembly', 'kind' => 'photo'])->assertSessionHasErrors('photo');
        $this->actingAs($u)->post('/submissions', [
            'title' => 'Assembly', 'kind' => 'photo', 'photo' => UploadedFile::fake()->image('a.jpg'),
        ])->assertSessionHasNoErrors();
        $s = $u->submissions()->first();
        $this->assertNotNull($s->photo_path);
        Storage::disk('magazines')->assertExists($s->photo_path);
    }
}
