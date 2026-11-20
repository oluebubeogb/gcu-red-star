<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->create(['username' => 'boss', 'role' => 'admin']); }

    private function edition(): Edition
    {
        $e = Edition::create(['title' => 'Harmattan 1987', 'slug' => 'harmattan-1987', 'year' => 1987, 'access' => 'public',
            'status' => 'published', 'published_at' => now()->subDay(), 'processing_status' => 'ready', 'page_count' => 3]);
        $e->pages()->create(['page_number' => 2, 'text' => 'The Elephant house won the inter-house sports']);
        return $e;
    }

    public function test_demo_seed_and_remove_never_touch_real_data(): void
    {
        $admin = $this->admin();
        $e = $this->edition();
        $real = User::factory()->create(['username' => 'real']);
        $realSub = $real->submissions()->create(['title' => 'Mine', 'kind' => 'poem', 'body' => 'x']);
        $realMark = $real->bookmarks()->create(['edition_id' => $e->id, 'page_number' => 1]);

        $this->actingAs($real)->post('/hq/demo', ['count' => 5])->assertForbidden();
        $this->actingAs($admin)->post('/hq/demo', ['count' => 26])->assertSessionHasErrors('count');

        $this->actingAs($admin)->post('/hq/demo', ['count' => 5])->assertSessionHasNoErrors();
        $demo = User::where('is_demo', true)->get();
        $this->assertCount(5, $demo);
        foreach ($demo as $u) {
            $this->assertNotNull($u->email_verified_at);
            $this->assertStringEndsWith('@demo.redstar.invalid', $u->email);
            $this->assertContains($u->category, ['student', 'old_boy', 'teacher', 'friend']);
            $this->assertFalse(Hash::check('password', $u->password));
        }
        $this->assertSame(0, Submission::where('is_demo', true)->whereNotIn('user_id', $demo->pluck('id'))->count());

        // Re-seeding replaces, it does not accumulate.
        $this->actingAs($admin)->post('/hq/demo', ['count' => 3]);
        $this->assertSame(3, User::where('is_demo', true)->count());

        $this->actingAs($admin)->delete('/hq/demo')->assertRedirect();
        $this->assertSame(0, User::where('is_demo', true)->count());
        $this->assertSame(0, Submission::where('is_demo', true)->count() + Bookmark::where('is_demo', true)->count());
        $this->assertModelExists($real);
        $this->assertModelExists($admin);
        $this->assertModelExists($realSub);
        $this->assertModelExists($realMark);
    }

    public function test_demo_tools_blocked_in_production_unless_allowed(): void
    {
        $admin = $this->admin();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->app['env'] = 'production';
        config(['redstar.allow_demo' => false]);
        $this->actingAs($admin)->post('/hq/demo', ['count' => 2])->assertForbidden();
        $this->actingAs($admin)->delete('/hq/demo')->assertForbidden();
        config(['redstar.allow_demo' => true]);
        $this->actingAs($admin)->post('/hq/demo', ['count' => 2])->assertRedirect();
        $this->assertSame(2, User::where('is_demo', true)->count());
    }

    public function test_friend_can_sign_up_and_use_member_features(): void
    {
        $this->get('/register')->assertInertia(fn (Assert $p) => $p->where('categories.3.value', 'friend')->where('categories.3.label', 'Friend of GCU'));
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'username' => 'xx1', 'category' => 'editorial',
            'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors('category');
        $this->post('/register', [
            'name' => 'Ada Friend', 'email' => 'ada@example.com', 'username' => 'adafriend', 'category' => 'friend',
            'graduation_year' => 1990, 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');
        $u = User::firstWhere('email', 'ada@example.com');
        $this->assertSame('friend', $u->category);
        $this->assertNull($u->graduation_year);

        $u->markEmailAsVerified();
        $this->actingAs($u)->post('/submissions', ['title' => 'From a parent', 'kind' => 'letter', 'body' => 'Hi'])->assertSessionHasNoErrors();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/hq')->assertInertia(fn (Assert $p) => $p->where('submissions.0.user.category', 'friend'));
        $this->actingAs($admin)->get('/hq/members?category=friend')->assertInertia(fn (Assert $p) => $p->has('members.data', 1));
    }

    public function test_search_suggests_and_corrects_typos(): void
    {
        $this->edition();
        $this->get('/search?q=elephnat')->assertInertia(fn (Assert $p) => $p
            ->where('didYouMean', 'elephant')->where('results.total', 1)->where('results.data.0.page', 2));
        $this->get('/search?q=harmatan')->assertInertia(fn (Assert $p) => $p->where('results.total', 0));
        $this->get('/search?q=Harmattan')->assertInertia(fn (Assert $p) => $p->where('editions.0.slug', 'harmattan-1987'));
    }

    public function test_edition_page_has_open_graph_and_share_url(): void
    {
        $this->edition();
        $this->get('/editions/harmattan-1987')->assertOk()
            ->assertSee('property="og:title" content="Harmattan 1987 — The Red Star"', false)
            ->assertInertia(fn (Assert $p) => $p->where('shareUrl', url('/editions/harmattan-1987')));
    }
}
