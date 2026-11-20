<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\Payment;
use App\Models\Sponsorship;
use App\Models\SponsorshipPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6MonnifyTest extends TestCase
{
    use RefreshDatabase;

    private array $tx = []; // paymentReference => [paymentStatus, amountPaid naira]

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'commerce.method' => 'monnify',
            'commerce.monnify.api_key' => 'MK_TEST_KEY',
            'commerce.monnify.secret' => 'monnify_secret',
            'commerce.monnify.contract_code' => '1234567890',
            'commerce.monnify.base_url' => 'https://sandbox.monnify.com',
            'commerce.paystack.secret' => 'sk_test_secret',
            'commerce.paystack.public' => 'pk_test_public',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'sandbox.monnify.com/*' => function (Request $r) { return $this->monnifyResponse($r); },
            'api.monnify.com/*' => function (Request $r) { return $this->monnifyResponse($r); },
            'api.paystack.co/*' => function (Request $r) {
                if (str_contains($r->url(), '/transaction/initialize')) {
                    return Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/'.$r['reference'], 'reference' => $r['reference']]]);
                }
                $ref = basename(parse_url($r->url(), PHP_URL_PATH));
                return Http::response(['status' => true, 'data' => ['id' => 99, 'reference' => $ref, 'status' => 'success', 'amount' => 5000000, 'currency' => 'NGN', 'channel' => 'card']]);
            },
        ]);
    }

    private function monnifyResponse(Request $r)
    {
        if (str_contains($r->url(), '/api/v1/auth/login')) {
            return Http::response(['requestSuccessful' => true, 'responseBody' => ['accessToken' => 'tok_test', 'expiresIn' => 3599]]);
        }
        if (str_contains($r->url(), 'init-transaction')) {
            $ref = $r['paymentReference'];
            $this->tx[$ref] ??= ['paymentStatus' => 'PAID', 'amountPaid' => $r['amount']];
            return Http::response(['requestSuccessful' => true, 'responseBody' => [
                'checkoutUrl' => 'https://sandbox.sdk.monnify.com/checkout/'.$ref,
                'transactionReference' => 'MNFY-'.$ref,
                'paymentReference' => $ref,
            ]]);
        }
        $ref = $r['paymentReference'] ?? basename(parse_url($r->url(), PHP_URL_PATH));
        if (str_starts_with((string) $ref, 'MNFY-')) $ref = substr($ref, 5);
        $t = $this->tx[$ref] ?? null;
        return $t
            ? Http::response(['requestSuccessful' => true, 'responseBody' => [
                'paymentReference' => $ref, 'transactionReference' => 'MNFY-'.$ref, 'currency' => 'NGN', 'paymentMethod' => 'CARD',
            ] + $t])
            : Http::response(['requestSuccessful' => false, 'responseMessage' => 'Not found'], 404);
    }

    private function sponsor(): Sponsorship
    {
        Edition::create(['title' => 'Speech Day 1976', 'slug' => 'speech-day-1976', 'year' => 1976, 'access' => 'public',
            'status' => 'published', 'published_at' => now()->subDay(), 'processing_status' => 'ready', 'page_count' => 4]);
        $pkg = SponsorshipPackage::create(['name' => 'Gold', 'target' => 'any', 'price_kobo' => 5000000, 'perks' => 'Logo']);
        $this->post('/sponsor', ['package_id' => $pkg->id, 'sponsor_type' => 'company', 'sponsor_name' => 'Acme Ltd',
            'target' => 'edition', 'edition_id' => Edition::first()->id, 'email' => 'acme@example.com'])
            ->assertRedirectContains('sandbox.sdk.monnify.com/checkout');
        return Sponsorship::first();
    }

    private function webhook(array $event, ?string $sig = 'auto', string $secret = 'monnify_secret')
    {
        $body = json_encode($event);
        $header = $sig === 'auto' ? hash_hmac('sha512', $body, $secret) : $sig;
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($header !== null) $headers['HTTP_MONNIFY_SIGNATURE'] = $header;
        return $this->call('POST', '/payments/webhook/monnify', [], [], [], $headers, $body);
    }

    public function test_init_sends_naira_and_verify_on_callback(): void
    {
        $s = $this->sponsor();
        $pay = Payment::first();
        $this->assertSame('monnify', $pay->gateway);
        $this->assertSame('MNFY-'.$pay->reference, $pay->paystack_id);
        $this->assertSame(5000000, $pay->amount_kobo);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/v1/auth/login')
            && str_contains(implode(' ', $r->header('Authorization') ?: []), 'Basic '.base64_encode('MK_TEST_KEY:monnify_secret')));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'init-transaction')
            && $r['amount'] === 50000.0 && $r['currencyCode'] === 'NGN' && $r['contractCode'] === '1234567890'
            && $r['paymentReference'] === $pay->reference && $r['customerEmail'] === 'acme@example.com'
            && $r['redirectUrl'] === route('payments.callback'));

        $this->get('/payments/callback?paymentReference='.$pay->reference)->assertRedirect(route('sponsor.index'))->assertSessionHas('status');
        $this->assertSame('success', $pay->fresh()->status);
        $this->assertSame('paid', $s->fresh()->status);
    }

    public function test_webhook_signature_valid_invalid_and_idempotency(): void
    {
        $s = $this->sponsor();
        $ref = Payment::first()->reference;
        $event = ['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => $ref, 'transactionReference' => 'MNFY-'.$ref, 'paymentStatus' => 'PAID',
            'amountPaid' => 50000, 'currency' => 'NGN', 'paymentMethod' => 'ACCOUNT_TRANSFER',
        ]];

        $this->webhook($event, 'bad-signature')->assertStatus(401);
        $this->webhook($event, hash_hmac('sha512', json_encode($event), 'wrong-secret'))->assertStatus(401);
        $this->assertSame('pending_payment', $s->fresh()->status);

        $this->webhook($event)->assertOk();
        $this->assertSame('paid', $s->fresh()->status);
        $this->assertSame('success', Payment::first()->status);
        $paidAt = $s->fresh()->paid_at;

        $this->travel(5)->minutes();
        $this->webhook($event)->assertOk();
        $this->get('/payments/callback?paymentReference='.$ref)->assertRedirect();
        $this->assertEquals($paidAt, $s->fresh()->paid_at);
        $this->assertSame(1, Payment::where('status', 'success')->count());

        $this->webhook(['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => 'RS-UNKNOWN', 'paymentStatus' => 'PAID', 'amountPaid' => 1, 'currency' => 'NGN',
        ]])->assertOk();
    }

    public function test_sandbox_unsigned_webhook_verifies_via_api(): void
    {
        $s = $this->sponsor();
        $ref = Payment::first()->reference;
        $event = ['eventType' => 'SUCCESSFUL_TRANSACTION', 'eventData' => [
            'paymentReference' => $ref, 'transactionReference' => 'MNFY-'.$ref, 'paymentStatus' => 'PAID', 'amountPaid' => 50000, 'currency' => 'NGN',
        ]];
        $this->webhook($event, null)->assertOk(); // no monnify-signature header
        $this->assertSame('paid', $s->fresh()->status);

        // Live mode (api.monnify.com) must reject a missing signature.
        config(['commerce.monnify.base_url' => 'https://api.monnify.com', 'commerce.monnify.api_key' => 'MK_PROD_KEY']);
        $this->webhook($event, null)->assertStatus(401);
    }

    public function test_underpaid_monnify_does_not_fulfil(): void
    {
        $s = $this->sponsor();
        $ref = Payment::first()->reference;
        $this->tx[$ref] = ['paymentStatus' => 'PAID', 'amountPaid' => 1]; // ₦1 instead of ₦50,000
        $this->get('/payments/callback?paymentReference='.$ref)->assertSessionHasErrors('payment');
        $this->assertSame('pending_payment', $s->fresh()->status);
        $this->assertNotSame('success', Payment::first()->status);
    }

    public function test_gateway_switching_and_selected_keys_required(): void
    {
        // Selected gateway is Monnify — Paystack keys alone are not enough.
        config(['commerce.monnify.api_key' => null, 'commerce.monnify.secret' => null, 'commerce.monnify.contract_code' => null]);
        $this->get('/sponsor')->assertOk()->assertInertia(fn (Assert $p) => $p->where('payments', false));
        $pkg = SponsorshipPackage::create(['name' => 'Gold', 'target' => 'any', 'price_kobo' => 5000000]);
        $this->post('/sponsor', ['package_id' => $pkg->id, 'sponsor_type' => 'company', 'sponsor_name' => 'Acme', 'target' => 'homecoming', 'email' => 'a@b.co'])->assertStatus(503);
        Http::assertNothingSent();

        // Switch to Paystack — checkout hits Paystack, not Monnify.
        config([
            'commerce.method' => 'paystack',
            'commerce.monnify.api_key' => 'MK_TEST_KEY',
            'commerce.monnify.secret' => 'monnify_secret',
            'commerce.monnify.contract_code' => '1234567890',
        ]);
        $this->get('/sponsor')->assertOk()->assertInertia(fn (Assert $p) => $p->where('payments', true));
        $this->post('/sponsor', ['package_id' => $pkg->id, 'sponsor_type' => 'company', 'sponsor_name' => 'Acme', 'target' => 'homecoming', 'email' => 'a@b.co'])
            ->assertRedirectContains('checkout.paystack.com');
        $this->assertSame('paystack', Payment::first()->gateway);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.paystack.co'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'init-transaction'));

        $admin = User::factory()->create(['username' => 'boss', 'role' => 'admin']);
        $this->actingAs($admin)->get('/hq/commerce')->assertInertia(fn (Assert $p) => $p
            ->where('gateway.method', 'paystack')->where('gateway.configured', true)->where('gateway.label', 'Paystack'));

        config(['commerce.method' => 'monnify']);
        $this->actingAs($admin)->get('/hq/commerce')->assertInertia(fn (Assert $p) => $p
            ->where('gateway.method', 'monnify')->where('gateway.test', true)
            ->where('gateway.webhook', route('payments.webhook.monnify')));
    }
}
