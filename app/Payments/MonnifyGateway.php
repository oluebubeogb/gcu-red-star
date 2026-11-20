<?php
namespace App\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Monnify: Basic apiKey:secretKey → /api/v1/auth/login (cached bearer token),
 * init with amount in Naira, verify by paymentReference or transactionReference,
 * webhook HMAC-SHA512 in header `monnify-signature` (production only — sandbox omits it).
 */
class MonnifyGateway implements PaymentGateway
{
    public function name(): string { return 'monnify'; }

    public function configured(): bool
    {
        return filled(config('commerce.monnify.api_key')) && filled(config('commerce.monnify.secret'))
            && filled(config('commerce.monnify.contract_code'));
    }

    public function testMode(): bool
    {
        $url = (string) config('commerce.monnify.base_url');
        $key = (string) config('commerce.monnify.api_key');
        return str_contains($url, 'sandbox') || str_starts_with($key, 'MK_TEST_');
    }

    public function webhookUrl(): string { return route('payments.webhook.monnify'); }

    private function token(): string
    {
        $cacheKey = 'monnify:token:'.sha1((string) config('commerce.monnify.api_key'));
        if ($t = Cache::get($cacheKey)) return $t;
        $basic = base64_encode(config('commerce.monnify.api_key').':'.config('commerce.monnify.secret'));
        $res = Http::baseUrl(config('commerce.monnify.base_url'))
            ->withHeaders(['Authorization' => 'Basic '.$basic])
            ->acceptJson()->asJson()->timeout(20)
            ->retry(2, 300, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false)
            ->post('/api/v1/auth/login');
        if (! $res->successful() || ! $res->json('requestSuccessful') || ! $res->json('responseBody.accessToken')) {
            throw new PaymentException('Monnify auth failed: '.($res->json('responseMessage') ?? $res->status()));
        }
        $ttl = max(60, (int) ($res->json('responseBody.expiresIn') ?? 3599) - 60);
        $token = (string) $res->json('responseBody.accessToken');
        Cache::put($cacheKey, $token, $ttl);
        return $token;
    }

    private function http()
    {
        return Http::baseUrl(config('commerce.monnify.base_url'))->withToken($this->token())
            ->acceptJson()->asJson()->timeout(20)->retry(2, 300, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false);
    }

    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = [], ?string $customerName = null): array
    {
        $res = $this->http()->post('/api/v1/merchant/transactions/init-transaction', [
            'amount' => round($amountKobo / 100, 2), // Monnify wants Naira, not kobo
            'currencyCode' => config('commerce.currency', 'NGN'),
            'contractCode' => config('commerce.monnify.contract_code'),
            'paymentReference' => $reference,
            'redirectUrl' => $callbackUrl,
            'customerName' => $customerName ?: (strtok($email, '@') ?: 'Red Star supporter'),
            'customerEmail' => $email,
            'paymentDescription' => $metadata['purpose'] ?? 'The Red Star',
            'metadata' => $metadata ?: new \stdClass,
        ]);
        if (! $res->successful() || ! $res->json('requestSuccessful') || ! $res->json('responseBody.checkoutUrl')) {
            throw new PaymentException('Monnify initialize failed: '.($res->json('responseMessage') ?? $res->status()));
        }
        return ['url' => $res->json('responseBody.checkoutUrl'), 'provider_id' => $res->json('responseBody.transactionReference')];
    }

    public function verify(string $reference, ?string $providerId = null): array
    {
        $http = $this->http();
        $res = $providerId
            ? $http->get('/api/v2/transactions/'.rawurlencode($providerId))
            : $http->get('/api/v2/merchant/transactions/query', ['paymentReference' => $reference]);
        if (! $res->successful() || ! $res->json('requestSuccessful')) {
            throw new PaymentException('Monnify verify failed: '.($res->json('responseMessage') ?? $res->status()));
        }
        return $this->normalize((array) $res->json('responseBody'));
    }

    /** Map a Monnify transaction or webhook eventData into the common kobo-based shape used by Payments::complete. */
    public function normalize(array $data): array
    {
        $status = strtoupper((string) ($data['paymentStatus'] ?? ''));
        $mapped = match ($status) {
            'PAID', 'OVERPAID' => 'success',
            'FAILED', 'REVERSED', 'EXPIRED', 'CANCELLED', 'CANCELED' => 'failed',
            default => $status !== '' ? strtolower($status) : 'pending',
        };
        return [
            'reference' => (string) ($data['paymentReference'] ?? ''),
            'status' => $mapped,
            'amount' => (int) round(((float) ($data['amountPaid'] ?? $data['amount'] ?? 0)) * 100),
            'currency' => strtoupper((string) ($data['currency'] ?? $data['currencyCode'] ?? 'NGN')),
            'id' => isset($data['transactionReference']) ? (string) $data['transactionReference'] : null,
            'channel' => $data['paymentMethod'] ?? null,
        ];
    }

    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('commerce.monnify.secret');
        return $secret !== '' && $signature !== null && $signature !== ''
            && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }
}
