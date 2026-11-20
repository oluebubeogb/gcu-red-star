<?php

namespace Tests\Feature;

use App\Mail\PrintOrderMail;
use App\Models\Ad;
use App\Models\Business;
use App\Models\Edition;
use App\Models\Payment;
use App\Models\PrintOrder;
use App\Models\Sponsorship;
use App\Models\SponsorshipPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6Test extends TestCase
{
    use RefreshDatabase;

    /** Paystack's view of each reference: status + amount (kobo). */
    private array $tx = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.paystack.secret' => 'sk_test_secret', 'commerce.paystack.public' => 'pk_test_public']);
        Http::preventStrayRequests();
        Http::fake(['api.paystack.co/*' => function (Request $r) {
            if (str_contains($r->url(), '/transaction/initialize')) {
                $this->tx[$r['reference']] ??= ['status' => 'success', 'amount' => $r['amount']];
                return Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/'.$r['reference'], 'reference' => $r['reference']]]);
            }
            $ref = basename(parse_url($r->url(), PHP_URL_PATH));
            $t = $this->tx[$ref] ?? null;
            return $t ? Http::response(['status' => true, 'data' => ['id' => 99, 'reference' => $ref, 'currency' => 'NGN', 'channel' => 'card'] + $t])
                : Http::response(['status' => false, 'message' => 'Transaction reference not found'], 400);
        }]);
    }

    private function member(string $u = 'm1', array $attrs = []): User
    {
        return User::factory()->create(['username' => $u] + $attrs);
    }

    private function admin(): User
    {
        return User::firstWhere('username', 'boss') ?? User::factory()->create(['username' => 'boss', 'role' => 'admin']);
    }

    private function edition(array $attrs = []): Edition
    {
        return Edition::create($attrs + ['title' => 'Speech Day 1976', 'slug' => 'speech-day-1976', 'year' => 1976, 'access' => 'public',
            'status' => 'published', 'published_at' => now()->subDay(), 'processing_status' => 'ready', 'page_count' => 4]);
    }

    private function webhook(array $event, ?string $sig = null)
    {
        $body = json_encode($event);
        return $this->call('POST', '/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig ?? hash_hmac('sha512', $body, 'sk_test_secret'),
        ], $body);
    }

    private function sponsor(): Sponsorship
    {
        $this->edition();
        $pkg = SponsorshipPackage::create(['name' => 'Gold', 'target' => 'any', 'price_kobo' => 5000000, 'perks' => 'Logo on edition']);
        $this->post('/sponsor', ['package_id' => $pkg->id, 'sponsor_type' => 'set', 'set_year' => 1976, 'sponsor_name' => 'Class of 1976',
            'target' => 'edition', 'edition_id' => Edition::first()->id, 'email' => 'set76@example.com'])
            ->assertRedirectContains('checkout.paystack.com');
        return Sponsorship::first();
    }

    public function test_missing_keys_hide_paid_features(): void
    {
        config(['commerce.paystack.secret' => null]);
        $e = $this->edition(['print_available' => true, 'print_price_kobo' => 250000, 'print_stock' => 5]);
        $this->get('/sponsor')->assertOk()->assertInertia(fn (Assert $p) => $p->where('payments', false));
        $pkg = SponsorshipPackage::create(['name' => 'Gold', 'target' => 'any', 'price_kobo' => 5000000]);
        $this->post('/sponsor', ['package_id' => $pkg->id, 'sponsor_type' => 'company', 'sponsor_name' => 'Acme', 'target' => 'homecoming', 'email' => 'a@b.co'])->assertStatus(503);
        $u = $this->member();
        $this->actingAs($u)->post("/editions/{$e->slug}/print", ['quantity' => 1, 'fulfilment' => 'pickup', 'name' => 'A', 'phone' => '080'])->assertStatus(503);
        // Free directory listing still works without payments.
        $this->actingAs($u)->post('/my/businesses', ['name' => 'Umuahia Farms', 'category' => 'Agriculture', 'description' => 'Fresh produce', 'state' => 'Abia'])->assertSessionHasNoErrors();
        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_payment_init_verify_on_callback_and_idempotency(): void
    {
        $s = $this->sponsor();
        $pay = Payment::first();
        $this->assertSame('pending', $pay->status);
        $this->assertSame(5000000, $pay->amount_kobo);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'initialize') && $r['amount'] === 5000000 && $r['currency'] === 'NGN'
            && $r->hasHeader('Authorization', 'Bearer sk_test_secret'));

        $this->get('/payments/callback?reference='.$pay->reference)->assertRedirect(route('sponsor.index'))->assertSessionHas('status');
        $this->assertSame('success', $pay->fresh()->status);
        $this->assertSame('paid', $s->fresh()->status);
        $paidAt = $s->fresh()->paid_at;

        // Replays (callback again + webhook) don't re-fulfil.
        $this->travel(5)->minutes();
        $this->get('/payments/callback?reference='.$pay->reference)->assertRedirect();
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => $pay->reference, 'status' => 'success', 'amount' => 5000000, 'currency' => 'NGN']])->assertOk();
        $this->assertEquals($paidAt, $s->fresh()->paid_at);
        $this->assertSame(1, Payment::where('status', 'success')->count());

        // Not credited until an editor approves name/logo.
        $this->get('/editions/speech-day-1976')->assertInertia(fn (Assert $p) => $p->has('sponsors', 0));
        $this->actingAs($this->admin())->put("/hq/commerce/sponsorships/{$s->id}", ['status' => 'approved', 'sponsor_name' => 'GCU Class of 1976'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/editions/speech-day-1976')->assertInertia(fn (Assert $p) => $p->where('sponsors.0.name', 'GCU Class of 1976'));
    }

    public function test_underpaid_or_failed_verification_does_not_fulfil(): void
    {
        $s = $this->sponsor();
        $ref = Payment::first()->reference;
        $this->tx[$ref] = ['status' => 'success', 'amount' => 100]; // tampered amount
        $this->get('/payments/callback?reference='.$ref)->assertSessionHasErrors('payment');
        $this->assertSame('pending_payment', $s->fresh()->status);
        $this->assertNotSame('success', Payment::first()->status);
    }

    public function test_webhook_signature_valid_and_invalid(): void
    {
        $s = $this->sponsor();
        $ref = Payment::first()->reference;
        $event = ['event' => 'charge.success', 'data' => ['reference' => $ref, 'status' => 'success', 'amount' => 5000000, 'currency' => 'NGN', 'id' => 7]];

        $this->webhook($event, 'bad-signature')->assertStatus(401);
        $this->webhook($event, hash_hmac('sha512', json_encode($event), 'wrong-secret'))->assertStatus(401);
        $this->assertSame('pending_payment', $s->fresh()->status);

        $this->webhook($event)->assertOk();
        $this->assertSame('paid', $s->fresh()->status);
        $this->webhook($event)->assertOk(); // duplicate delivery
        $this->assertSame(1, Payment::where('status', 'success')->count());
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => 'RS-UNKNOWN', 'status' => 'success', 'amount' => 1]])->assertOk();
    }

    public function test_directory_approval_flow_and_featured_expiry(): void
    {
        $u = $this->member('biz', ['admission_year' => 1980, 'show_in_directory' => true]);
        $this->actingAs($u)->post('/my/businesses', ['name' => 'Ikemba Logistics', 'category' => 'Logistics & Transport',
            'description' => 'Haulage across the South-East', 'state' => 'Enugu', 'whatsapp' => '+2348000000000'])->assertSessionHasNoErrors();
        $b = Business::first();
        $this->assertSame('pending', $b->status);
        auth()->logout();
        $this->get("/directory")->assertInertia(fn (Assert $p) => $p->where('businesses.total', 0));

        $this->actingAs($this->member('nope'))->put("/hq/commerce/businesses/{$b->id}", ['status' => 'approved'])->assertForbidden();
        $this->actingAs($this->admin())->put("/hq/commerce/businesses/{$b->id}", ['status' => 'approved'])->assertSessionHasNoErrors();
        auth()->logout();
        $this->get('/directory?state=Enugu&category=Logistics+%26+Transport')->assertInertia(fn (Assert $p) => $p
            ->where('businesses.total', 1)->where('businesses.data.0.owner.username', 'biz')->where('businesses.data.0.owner.admission_year', 1980)
            ->where('businesses.data.0.featured', false));
        $this->get('/directory?state=Lagos')->assertInertia(fn (Assert $p) => $p->where('businesses.total', 0));

        // Pay for 3 months featured.
        $this->actingAs($u)->post("/my/businesses/{$b->slug}/feature", ['months' => 3])->assertRedirectContains('checkout.paystack.com');
        $pay = Payment::first();
        $this->assertSame(1500000, $pay->amount_kobo);
        $this->get('/payments/callback?reference='.$pay->reference);
        $this->assertTrue($b->fresh()->isFeatured());
        $this->assertTrue($b->fresh()->featured_until->between(now()->addMonths(3)->subMinute(), now()->addMonths(3)->addMinute()));
        $this->get('/directory')->assertInertia(fn (Assert $p) => $p->where('businesses.data.0.featured', true));

        $this->travel(3)->months();
        $this->travel(1)->days();
        $this->assertFalse($b->fresh()->isFeatured());
        $this->get('/directory')->assertInertia(fn (Assert $p) => $p->where('businesses.data.0.featured', false));

        // Edits go back to review.
        $this->actingAs($u)->post("/my/businesses/{$b->slug}", ['name' => 'Ikemba Logistics Ltd', 'category' => 'Logistics & Transport',
            'description' => 'Haulage', 'state' => 'Enugu'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $b->fresh()->status);
    }

    public function test_ad_approval_rotation_audience_and_digest(): void
    {
        $buy = function (string $title, string $audience = 'all') {
            $this->post('/advertise', ['slot_key' => 'homepage_banner', 'kind' => 'ad', 'advertiser' => 'Acme', 'email' => 'ads@acme.ng',
                'title' => $title, 'link_url' => 'https://acme.ng', 'audience' => $audience, 'starts_on' => today()->toDateString(), 'periods' => 1])->assertRedirectContains('checkout.paystack.com');
            $ad = Ad::latest('id')->first();
            $this->get('/payments/callback?reference='.Payment::latest('id')->first()->reference);
            return $ad->fresh();
        };
        $a = $buy('Banner A');
        $this->assertSame('paid', $a->status);
        $this->assertSame(3000000, $a->amount_kobo);
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('banner', null)); // not approved yet

        $admin = $this->admin();
        $this->actingAs($admin)->put("/hq/commerce/ads/{$a->id}", ['status' => 'approved'])->assertSessionHasNoErrors();
        auth()->logout();
        $b = $buy('Banner B');
        $this->actingAs($admin)->put("/hq/commerce/ads/{$b->id}", ['status' => 'approved']);
        auth()->logout();

        $seen = [];
        foreach (range(1, 4) as $i) $this->get('/')->assertInertia(function (Assert $p) use (&$seen) { $seen[] = $p->toArray()['props']['banner']['title']; });
        $this->assertEqualsCanonicalizing(['Banner A', 'Banner B', 'Banner A', 'Banner B'], $seen); // rotates
        $this->assertSame(2, $a->fresh()->impressions);

        $this->get("/ads/{$a->id}/click")->assertRedirect('https://acme.ng');
        $this->assertSame(1, $a->fresh()->clicks);

        // Members-only creative never shown to guests.
        $m = $buy('Members only', 'members');
        $this->actingAs($admin)->put("/hq/commerce/ads/{$m->id}", ['status' => 'approved']);
        auth()->logout();
        foreach (range(1, 4) as $i) $this->get('/')->assertInertia(fn (Assert $p) => $p->whereNot('banner.title', 'Members only'));

        // Expired ads stop showing.
        $this->travel(31)->days();
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('banner', null));

        // Digest slot.
        $this->travelBack();
        $d = Ad::create(['slot_key' => 'digest', 'advertiser' => 'Bank', 'email' => 'x@y.z', 'title' => 'Save with us', 'starts_on' => today(), 'ends_on' => today()->addDays(29),
            'amount_kobo' => 1, 'status' => 'approved', 'paid_at' => now()]);
        $this->assertSame('Save with us', \App\Support\Digest::content()['ad']['title']);
        $html = (new \App\Mail\MonthlyDigest($this->member('dig'), \App\Support\Digest::content()))->render();
        $this->assertStringContainsString('Save with us', $html);
    }

    public function test_print_order_flow_and_stock_decrement(): void
    {
        Mail::fake();
        $e = $this->edition(['print_available' => true, 'print_price_kobo' => 250000, 'print_stock' => 3]);
        $u = $this->member();
        $this->actingAs($u)->post("/editions/{$e->slug}/print", ['quantity' => 4, 'fulfilment' => 'pickup', 'name' => 'Chidi', 'phone' => '0803'])->assertSessionHasErrors('quantity');
        $this->actingAs($u)->post("/editions/{$e->slug}/print", ['quantity' => 2, 'fulfilment' => 'delivery', 'name' => 'Chidi', 'phone' => '0803'])->assertSessionHasErrors('address');
        $this->actingAs($u)->post("/editions/{$e->slug}/print", ['quantity' => 2, 'fulfilment' => 'delivery', 'name' => 'Chidi', 'phone' => '0803',
            'address' => '12 Ikot Ekpene Rd', 'city' => 'Umuahia', 'state' => 'Abia'])->assertRedirectContains('checkout.paystack.com');
        $o = PrintOrder::first();
        $this->assertSame(500000, $o->amount_kobo);
        $this->assertSame(3, $e->fresh()->print_stock); // not decremented before payment

        $ref = Payment::first()->reference;
        $this->get('/payments/callback?reference='.$ref);
        $this->webhook(['event' => 'charge.success', 'data' => ['reference' => $ref, 'status' => 'success', 'amount' => 500000, 'currency' => 'NGN']]);
        $this->assertSame('paid', $o->fresh()->status);
        $this->assertSame(1, $e->fresh()->print_stock); // decremented once
        Mail::assertQueued(PrintOrderMail::class, 1);

        $admin = $this->admin();
        foreach (['printing', 'shipped', 'delivered'] as $st) {
            $this->actingAs($admin)->put("/hq/commerce/orders/{$o->id}", ['status' => $st, 'tracking_note' => 'GIG Logistics #123'])->assertSessionHasNoErrors();
        }
        $this->assertSame('delivered', $o->fresh()->status);
        Mail::assertQueued(PrintOrderMail::class, 4);
        $this->actingAs($u)->get('/orders')->assertInertia(fn (Assert $p) => $p->where('orders.0.status', 'delivered')->where('orders.0.tracking_note', 'GIG Logistics #123'));

        // Revenue dashboard + CSV.
        $this->actingAs($admin)->get('/hq/revenue')->assertInertia(fn (Assert $p) => $p->where('totals.print', 500000)->where('months.11.print', 500000));
        $csv = $this->actingAs($admin)->get('/hq/revenue/export')->assertOk()->streamedContent();
        $this->assertStringContainsString($ref.',', $csv);
        $this->assertStringContainsString(',paystack,print,5000.00,NGN,', $csv);
    }

    public function test_bulk_upload_creates_drafts_and_queues(): void
    {
        Storage::fake('magazines');
        config(['redstar.magazine_disk' => 'magazines', 'redstar.pdf_queue' => 'redstar:pdf']);
        Redis::shouldReceive('rpush')->times(3);
        $admin = $this->admin();
        $csv = "filename,title,date,volume,issue,members_only\nharmattan-1965.pdf,Harmattan Number,1965-02-01,12,1,yes\n";
        $this->actingAs($admin)->post('/hq/bulk/upload', [
            'pdfs' => [UploadedFile::fake()->create('harmattan-1965.pdf', 100, 'application/pdf'), UploadedFile::fake()->create('speech_day_1971.pdf', 100, 'application/pdf')],
            'csv' => $csv, 'batch' => 'old-batch', 'auto_publish' => true,
        ])->assertSessionHasNoErrors();
        $a = Edition::where('title', 'Harmattan Number')->first();
        $this->assertSame([1965, 'members', 'draft', 'pending', '12', '1965-02-01'], [$a->year, $a->access, $a->status, $a->processing_status, $a->volume, $a->issue_date->toDateString()]);
        $b = Edition::where('title', 'Speech Day 1971')->first();
        $this->assertSame(1971, $b->year);
        $this->assertSame('public', $b->access);
        Storage::disk('magazines')->assertExists($a->pdf_path);

        // Folder already on the volume.
        Storage::disk('magazines')->put('imports/box1/Founders Day 1980.pdf', '%PDF-1.4');
        $this->actingAs($admin)->post('/hq/bulk/folder', ['folder' => 'imports/box1'])->assertSessionHasNoErrors();
        $c = Edition::where('year', 1980)->first();
        Storage::disk('magazines')->assertExists($c->pdf_path);
        Storage::disk('magazines')->assertMissing('imports/box1/Founders Day 1980.pdf');
        $this->actingAs($admin)->post('/hq/bulk/folder', ['folder' => 'imports/../editions'])->assertSessionHasErrors('folder');

        $this->actingAs($admin)->get('/hq/bulk')->assertInertia(fn (Assert $p) => $p->has('editions', 3));

        // Auto-publish once the worker marks it ready.
        $a->forceFill(['processing_status' => 'ready', 'page_count' => 8])->save();
        $this->artisan('redstar:auto-publish')->assertSuccessful();
        $this->assertSame('published', $a->fresh()->status);
        $this->assertSame('draft', $b->fresh()->status); // still processing
    }
}
