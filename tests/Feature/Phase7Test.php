<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Event;
use App\Models\User;
use App\Storage\MagazineStorage;
use App\Support\DemoData;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase7Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['username' => 'boss7', 'role' => 'admin']);
    }

    public function test_seeded_emails_password_and_all_male_roster(): void
    {
        $this->actingAs($this->admin())->post('/hq/demo', ['count' => 8])->assertSessionHasNoErrors();
        $users = User::where('is_demo', true)->orderBy('id')->get();
        $this->assertCount(8, $users);
        $this->assertTrue($users->contains(fn ($u) => $u->email === 'chukwuemeka.gcu@collab.name.ng'));
        $this->assertTrue($users->contains(fn ($u) => $u->email === 'chukwuemeka.nwosu.gcu@collab.name.ng'));
        $this->assertTrue($users->contains(fn ($u) => $u->admission_year === 1975));
        $this->assertTrue($users->contains(fn ($u) => $u->admission_year === 1976));
        $this->assertGreaterThanOrEqual(5, $users->whereNotNull('house')->pluck('house')->unique()->count());
        foreach ($users as $u) {
            $this->assertTrue(Hash::check('P@ssw0rd', $u->password));
            $this->assertSame('he/him', $u->pronouns);
            $this->assertNotEmpty($u->bio);
            $this->assertMatchesRegularExpression('/\b([Hh]e|[Hh]im)\b/', $u->bio);
            $this->assertNotNull($u->avatar_path);
            $this->assertTrue(app(MagazineStorage::class)->exists($u->avatar_path));
            $this->assertContains(explode(' ', $u->name)[0], DemoData::FIRST_NAMES);
        }
        $this->assertTrue($users->pluck('house')->filter()->every(fn ($h) => in_array($h, Roles::HOUSES, true)));
    }

    public function test_seeded_editions_have_local_art_and_are_searchable(): void
    {
        $this->actingAs($this->admin())->post('/hq/demo', ['count' => 5])->assertSessionHasNoErrors();
        $editions = Edition::where('is_demo', true)->get();
        $this->assertGreaterThanOrEqual(3, $editions->count());
        $storage = app(MagazineStorage::class);
        foreach ($editions as $e) {
            $this->assertSame('ready', $e->processing_status);
            $this->assertSame('published', $e->status);
            $this->assertNotNull($e->cover_path);
            $this->assertNotNull($e->pdf_path);
            $this->assertTrue($storage->exists($e->cover_path));
            $this->assertTrue($storage->exists($e->pdf_path));
            $this->assertStringStartsWith('%PDF', $storage->get($e->pdf_path));
            $this->assertGreaterThanOrEqual(6, $e->pages()->count());
            $this->assertGreaterThanOrEqual(6, $e->blocks()->count());
            $page = $e->pages()->first();
            $this->assertNotNull($page->image_key);
            $this->assertTrue($storage->exists($page->image_key));
            $this->assertStringStartsWith('seed/', $page->image_key);
        }

        $this->get('/search?q='.rawurlencode('Class of 1975 hosted the Government College Umuahia Old Boys Association Homecoming'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Public/Search')->where('results.total', fn ($n) => $n >= 1));

        $pub = Edition::where('is_demo', true)->where('access', 'public')->first();
        $this->assertNotNull($pub);
        auth()->logout();
        $this->flushSession();
        $this->get('/editions/'.$pub->slug)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('canRead', true)
            ->has('pages.0.image_url')
            ->where('canDownload', false)
            ->has('blocks'));

        $member = User::where('is_demo', true)->first();
        $this->actingAs($member)->get('/editions/'.$pub->slug)
            ->assertInertia(fn (Assert $p) => $p->where('canDownload', true));
        $this->get('/members/'.$member->username)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('member.pronouns', 'he/him')
            ->where('member.avatar', fn ($a) => is_string($a) && $a !== '')
            ->where('member.bio', fn ($b) => is_string($b) && $b !== ''));
    }

    public function test_purge_removes_seeded_files_and_leaves_real_records(): void
    {
        $admin = $this->admin();
        $real = Edition::create([
            'title' => 'Harmattan 1987', 'slug' => 'harmattan-1987', 'year' => 1987,
            'access' => 'public', 'status' => 'published', 'published_at' => now()->subDay(),
            'processing_status' => 'ready', 'page_count' => 1,
        ]);
        $this->actingAs($admin)->post('/hq/demo', ['count' => 4])->assertSessionHasNoErrors();
        $keys = [];
        foreach (Edition::where('is_demo', true)->get() as $e) {
            $keys[] = $e->cover_path;
            $keys[] = $e->pdf_path;
            foreach ($e->pages as $page) $keys[] = $page->image_key;
        }
        foreach (User::where('is_demo', true)->pluck('avatar_path') as $k) $keys[] = $k;
        $keys = array_values(array_filter(array_unique($keys)));
        $this->assertNotEmpty($keys);

        $this->actingAs($admin)->delete('/hq/demo')->assertRedirect();
        $this->assertSame(0, User::where('is_demo', true)->count());
        $this->assertSame(0, Edition::where('is_demo', true)->count());
        $this->assertSame(0, Event::where('is_demo', true)->count());
        $storage = app(MagazineStorage::class);
        foreach ($keys as $key) {
            $this->assertFalse($storage->exists($key), "leftover seed file: $key");
        }
        $this->assertModelExists($real);
        $this->assertTrue(Event::where('slug', 'homecoming-2025-class-of-1975')->exists());
    }

    public function test_public_frontend_copy_avoids_seed_labels(): void
    {
        $roots = [
            resource_path('js/Pages/Public'),
            resource_path('js/Pages/Community'),
            resource_path('js/Pages/Member'),
            resource_path('js/Pages/Auth'),
            resource_path('js/Pages/Profile'),
            resource_path('js/Components'),
            resource_path('js/Layouts'),
            resource_path('js/Pages/Dashboard.vue'),
        ];
        $files = [];
        foreach ($roots as $root) {
            if (is_dir($root)) $files = array_merge($files, $this->vueFiles($root));
            elseif (is_file($root)) $files[] = $root;
        }
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $visible = $this->visibleVueCopy($file);
            $this->assertDoesNotMatchRegularExpression(
                '/\b(demo|sample|dummy|test)\b/i',
                $visible,
                basename($file).' exposes a seed-style label on the public frontend'
            );
        }
    }

    public function test_hq_seed_controls_use_neutral_labels(): void
    {
        $index = file_get_contents(resource_path('js/Pages/Hq/Index.vue'));
        $members = file_get_contents(resource_path('js/Pages/Hq/Members.vue'));
        $this->assertStringContainsString('Seed content', $index);
        $this->assertStringContainsString('Remove seeded content', $index);
        $this->assertStringContainsString('>seeded</span>', $index);
        $this->assertStringNotContainsString('Demo data', $index);
        $this->assertStringNotContainsString('Seed demo data', $index);
        $this->assertStringNotContainsString('dummy', strtolower($index));
        $this->assertStringContainsString('Hide seeded', $members);
        $this->assertStringContainsString('Seeded only', $members);
        $this->assertStringContainsString('>seeded</span>', $members);
        $this->assertStringNotContainsString('Real + demo', $members);
        $this->assertStringNotContainsString('Demo only', $members);
    }

    /** @return list<string> */
    private function vueFiles(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'vue') $out[] = $f->getPathname();
        }
        return $out;
    }

    /** Template text with scripts, bindings and interpolations removed. */
    private function visibleVueCopy(string $file): string
    {
        $src = file_get_contents($file);
        $src = preg_replace('/<script\b[^>]*>.*?<\/script>/si', ' ', $src);
        $src = preg_replace('/\{\{.*?\}\}/s', ' ', $src);
        $src = preg_replace('/\s(?:v-|:|@)[\w:.-]+="[^"]*"/', ' ', $src);
        $src = preg_replace('/<!--.*?-->/s', ' ', $src);
        return $src;
    }
}
