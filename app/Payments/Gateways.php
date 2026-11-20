<?php
namespace App\Payments;

/** Resolves the active payment provider from PAYMENT_METHOD (default paystack). */
class Gateways
{
    public function current(): PaymentGateway
    {
        return $this->get((string) config('commerce.method', 'paystack'));
    }

    public function get(string $name): PaymentGateway
    {
        return match (strtolower($name)) {
            'monnify' => app(MonnifyGateway::class),
            default => app(PaystackGateway::class),
        };
    }

    public function enabled(): bool { return $this->current()->configured(); }

    public function status(): array
    {
        $g = $this->current();
        return [
            'method' => $g->name(),
            'label' => $g->name() === 'monnify' ? 'Monnify' : 'Paystack',
            'configured' => $g->configured(),
            'test' => $g->testMode(),
            'webhook' => $g->webhookUrl(),
        ];
    }
}
