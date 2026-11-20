<?php

namespace Tests\Feature;

use App\Mail\MonthlyDigest;
use App\Models\Edition;
use App\Models\Event;
use App\Models\User;
use App\Notifications\TaggedOnPage;
use App\Support\Homecoming;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase4Test extends TestCase
{
    use RefreshDatabase;

    private function edition(): Edition
    {
        return Edition::create(['title' => 'Speech Day 1976', 'slug' => 'speech-day-1976', 'year' => 1976, 'access' => 'public',
            'status' => 'published', 'published_at' => now()->subDay(), 'processing_status' => 'ready', 'page_count' => 4]);
    }

    public function test_house_is_validated_against_the_fixed_list(): void
    {
        $this->assertSame('Niger House', Roles::normalizeHouse(' niger '));
        $this->assertSame('School House', Roles::normalizeHouse('SCHOOL HOUSE'));
        $this->assertNull(Roles::normalizeHouse('Unity House'));

        $u = User::factory()->create(['username' => 'h1']);
        $this->actingAs($u)->patch('/profile', ['name' => $u->name, 'email' => $u->email, 'house' => 'Unity House'])->assertSessionHasErrors('house');
        $this->actingAs($u)->patch('/profile', ['name' => $u->name, 'email' => $u->email, 'house' => 'Fisher House', 'admission_year' => 1976])->assertSessionHasNoErrors();
        $this->assertSame('Fisher House', $u->fresh()->house);
        $this->assertSame(1976, $u->fresh()->admission_year);

        $this->actingAs(User::factory()->create(['username' => 'boss', 'role' => 'admin']))
            ->get('/hq/members?house=Fisher+House')->assertInertia(fn (Assert $p) => $p->has('members.data', 1)->where('members.data.0.username', 'h1'));
        $this->get('/houses/fisher')->assertOk();
    }

    public function test_tagging_notifies_member_and_respects_removal_rules(): void
    {
        Notification::fake();
        $e = $this->edition();
        $tagger = User::factory()->create(['username' => 'tagger']);
        $obi = User::factory()->create(['username' => 'obi', 'admission_year' => 1976]);
        $other = User::factory()->create(['username' => 'other']);

        $this->actingAs($tagger)->post("/editions/{$e->slug}/tags", ['page_number' => 2, 'username' => '@obi', 'note' => 'front row'])->assertSessionHasNoErrors();
        $tag = $obi->tags()->first();
        $this->assertNotNull($tag);
        Notification::assertSentTo($obi, TaggedOnPage::class, fn ($n, $channels) => in_array('database', $channels) && in_array('mail', $channels));

        $this->actingAs($tagger)->post("/editions/{$e->slug}/tags", ['page_number' => 9, 'username' => 'obi'])->assertSessionHasErrors('page_number');
        $this->actingAs($tagger)->post("/editions/{$e->slug}/tags", ['page_number' => 1, 'username' => 'nobody'])->assertSessionHasErrors('username');

        // Free-text tag, claimed by the person.
        $this->actingAs($tagger)->post("/editions/{$e->slug}/tags", ['page_number' => 3, 'name' => 'Emeka Okafor']);
        $free = \App\Models\PageTag::whereNull('user_id')->first();
        $this->actingAs($other)->post("/tags/{$free->id}/claim")->assertRedirect();
        $this->assertSame($other->id, $free->fresh()->user_id);

        $this->get('/editions/speech-day-1976')->assertInertia(fn (Assert $p) => $p->has('tags', 2));
        $this->get('/members/obi')->assertInertia(fn (Assert $p) => $p->has('tags', 1)->where('tags.0.page', 2));

        $this->actingAs($tagger)->delete("/tags/{$tag->id}")->assertForbidden();
        $this->actingAs($obi)->delete("/tags/{$tag->id}")->assertRedirect();
        $this->assertModelMissing($tag);
        $editor = User::factory()->create(['username' => 'ed', 'role' => 'editor']);
        $this->actingAs($editor)->delete('/tags/'.$free->id)->assertRedirect();
        $this->assertModelMissing($free);
    }

    public function test_tag_notification_is_stored_in_database(): void
    {
        $e = $this->edition();
        $obi = User::factory()->create(['username' => 'obi', 'notify_email' => false]);
        $this->actingAs(User::factory()->create(['username' => 't2']))->post("/editions/{$e->slug}/tags", ['page_number' => 1, 'username' => 'obi']);
        $this->assertSame(1, $obi->unreadNotifications()->count());
        $this->actingAs($obi)->get('/notifications')->assertInertia(fn (Assert $p) => $p->has('notifications', 1));
    }

    public function test_set_and_profile_pages_respect_privacy(): void
    {
        User::factory()->create(['username' => 'pub', 'admission_year' => 1976, 'show_in_directory' => true]);
        $hidden = User::factory()->create(['username' => 'hid', 'admission_year' => 1976, 'show_in_directory' => false]);
        User::factory()->unverified()->create(['username' => 'unv', 'admission_year' => 1976]);

        $this->get('/sets/1976')->assertOk()->assertInertia(fn (Assert $p) => $p->has('members', 1)->where('members.0.username', 'pub'));
        $this->get('/sets')->assertInertia(fn (Assert $p) => $p->where('sets.1976', 1));
        $this->get('/members/pub')->assertOk();
        $this->get('/members/hid')->assertNotFound();
        $this->actingAs($hidden)->get('/members/hid')->assertOk();
        $this->get('/sets/1800')->assertNotFound();
    }

    public function test_homecoming_jubilee_set_is_admission_year_plus_fifty(): void
    {
        $this->assertSame(1976, Homecoming::jubileeSetFor(2026));
        $this->assertDatabaseHas('events', ['set_year' => 1975, 'starts_on' => '2025-12-12']); // seeded real record

        Carbon::setTestNow('2025-06-01');
        $this->assertSame(1975, Homecoming::upcomingSet());
        Carbon::setTestNow('2025-12-20'); // after the 12–14 Dec 2025 event
        $this->assertSame(1976, Homecoming::upcomingSet());
        Carbon::setTestNow('2026-03-01');
        $this->assertSame(1976, Homecoming::upcomingSet());

        User::factory()->create(['username' => 'j76', 'admission_year' => 1976]);
        $this->get('/homecoming')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('set', 1976)->where('year', 2026)->has('members', 1)->where('past.0.set_year', 1975));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('homecoming.set', 1976));
        Carbon::setTestNow();
    }

    public function test_digest_goes_only_to_opted_in_members_and_unsubscribe_works(): void
    {
        Mail::fake();
        $in = User::factory()->create(['username' => 'in', 'digest_opt_in' => true]);
        $out = User::factory()->create(['username' => 'out', 'digest_opt_in' => false]);
        User::factory()->unverified()->create(['username' => 'unv', 'digest_opt_in' => true]);

        $this->artisan('redstar:digest')->assertSuccessful();
        Mail::assertQueued(MonthlyDigest::class, 1);
        Mail::assertQueued(MonthlyDigest::class, fn ($m) => $m->hasTo($in->email));
        Mail::assertNotQueued(MonthlyDigest::class, fn ($m) => $m->hasTo($out->email));

        $this->get("/digest/unsubscribe/{$in->id}")->assertForbidden(); // unsigned
        $this->get(URL::signedRoute('digest.unsubscribe', ['user' => $in->id]))->assertOk();
        $this->assertFalse($in->fresh()->digest_opt_in);

        $editor = User::factory()->create(['username' => 'ed', 'role' => 'editor']);
        $this->actingAs($editor)->post('/hq/digest/test')->assertRedirect();
        Mail::assertSent(MonthlyDigest::class, fn ($m) => $m->hasTo($editor->email));
        $this->assertStringContainsString('Unsubscribe', (new MonthlyDigest($in, \App\Support\Digest::content()))->render());
    }

    public function test_student_badges_and_friend_ineligible(): void
    {
        $s = User::factory()->create(['username' => 'stu', 'category' => 'student']);
        $f = User::factory()->create(['username' => 'fri', 'category' => 'friend']);
        foreach ([$s, $f] as $u) $u->submissions()->create(['title' => 'x', 'kind' => 'poem', 'body' => 'y', 'status' => 'accepted']);
        $this->get('/members/stu')->assertInertia(fn (Assert $p) => $p->where('contributions.badges.0', 'Student Contributor')->where('contributions.accepted', 1));
        $this->get('/members/fri')->assertInertia(fn (Assert $p) => $p->where('contributions.badges', [])->where('contributions.accepted', 1));
    }

    public function test_hq_manages_events_announcements_and_feature(): void
    {
        Carbon::setTestNow('2026-11-20 10:00');
        $ed = User::factory()->create(['username' => 'ed', 'role' => 'editor']);
        $m = User::factory()->create(['username' => 'chinedu']);
        $this->actingAs($m)->get('/hq/community')->assertForbidden();
        $this->actingAs($ed)->post('/hq/events', ['title' => 'Homecoming 2026', 'set_year' => 1976, 'starts_on' => '2026-12-11', 'ends_on' => '2026-12-13', 'published' => true])->assertSessionHasNoErrors();
        $this->actingAs($ed)->post('/hq/announcements', ['title' => 'AGM notice', 'body' => 'All old boys welcome', 'published' => true])->assertSessionHasNoErrors();
        $this->actingAs($ed)->post('/hq/features', ['username' => 'chinedu', 'month' => now()->format('Y-m'), 'blurb' => 'Engineer and mentor'])->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-11-20 12:00');
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('homecoming.set', 1976)->where('homecoming.event.title', 'Homecoming 2026')
            ->where('announcements.0.title', 'AGM notice'));
        Carbon::setTestNow();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('feature.member.username', 'chinedu'));
    }
}
