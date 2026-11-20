<?php
namespace App\Payments;

/** A payment provider (Paystack or Monnify). Amounts are always kobo inside the app. */
interface PaymentGateway
{
    public function name(): string;

    public function configured(): bool;

    public function testMode(): bool;

    public function webhookUrl(): string;

    /**
     * Start a checkout session.
     * @return array{url: string, provider_id: ?string}
     */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = [], ?string $customerName = null): array;

    /**
     * Server-side verification.
     * @return array{reference: string, status: string, amount: int, currency: string, id: ?string, channel: ?string}
     */
    public function verify(string $reference, ?string $providerId = null): array;

    public function validSignature(string $payload, ?string $signature): bool;
}
