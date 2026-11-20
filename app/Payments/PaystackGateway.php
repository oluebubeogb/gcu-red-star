<?php
namespace App\Payments;

use Illuminate\Support\Facades\Http;

/** Paystack: initialize → redirect, verify, webhook HMAC-SHA512 (`x-paystack-signature`). Amounts are kobo. */
class PaystackGateway implements PaymentGateway
{
    public function name(): string { return 'paystack'; }

    public function configured(): bool
    {
        return filled(config('commerce.paystack.secret')) && filled(config('commerce.paystack.public'));
    }

    public function testMode(): bool
    {
        return str_starts_with((string) config('commerce.paystack.secret'), 'sk_test_');
    }

    public function webhookUrl(): string { return route('payments.webhook'); }

    private function http()
    {
        return Http::baseUrl(config('commerce.paystack.base_url'))->withToken(config('commerce.paystack.secret'))
            ->acceptJson()->asJson()->timeout(20)->retry(2, 300, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false);
    }

    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = [], ?string $customerName = null): array
    {
        $res = $this->http()->post('/transaction/initialize', [
            'email' => $email, 'amount' => $amountKobo, 'currency' => config('commerce.currency'),
            'reference' => $reference, 'callback_url' => $callbackUrl,
            'metadata' => $metadata + array_filter(['customer_name' => $customerName]),
        ]);
        if (! $res->successful() || ! $res->json('status') || ! $res->json('data.authorization_url')) {
            throw new PaymentException('Paystack initialize failed: '.($res->json('message') ?? $res->status()));
        }
        return ['url' => $res->json('data.authorization_url'), 'provider_id' => $res->json('data.reference')];
    }

    public function verify(string $reference, ?string $providerId = null): array
    {
        $res = $this->http()->get('/transaction/verify/'.rawurlencode($reference));
        if (! $res->successful() || ! $res->json('status')) {
            throw new PaymentException('Paystack verify failed: '.($res->json('message') ?? $res->status()));
        }
        $d = (array) $res->json('data');
        return [
            'reference' => (string) ($d['reference'] ?? $reference),
            'status' => (string) ($d['status'] ?? ''),
            'amount' => (int) ($d['amount'] ?? 0),
            'currency' => strtoupper((string) ($d['currency'] ?? 'NGN')),
            'id' => isset($d['id']) ? (string) $d['id'] : null,
            'channel' => $d['channel'] ?? null,
        ];
    }

    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('commerce.paystack.secret');
        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }
}
