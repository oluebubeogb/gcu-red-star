<?php
namespace App\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Creates payment records, starts checkout on the selected gateway, and fulfils exactly once (callback + webhook). */
class Payments
{
    public function __construct(private Gateways $gateways) {}

    public function enabled(): bool { return $this->gateways->enabled(); }

    /** @return string checkout URL */
    public function start(Model&Payable $payable, string $purpose, int $amountKobo, string $email, ?User $user = null, array $meta = []): string
    {
        $gateway = $this->gateways->current();
        if (! $gateway->configured()) throw new PaymentException('Online payments are not configured.');
        $payment = Payment::create([
            'reference' => 'RS-'.strtoupper($purpose[0]).'-'.Str::upper(Str::random(16)),
            'user_id' => $user?->id, 'email' => $email, 'purpose' => $purpose,
            'payable_type' => $payable->getMorphClass(), 'payable_id' => $payable->getKey(),
            'amount_kobo' => $amountKobo, 'currency' => config('commerce.currency'),
            'gateway' => $gateway->name(), 'meta' => $meta ?: null,
        ]);
        try {
            $started = $gateway->initialize($email, $amountKobo, $payment->reference, route('payments.callback'),
                ['purpose' => $purpose, 'payable_id' => $payable->getKey(), 'payment_id' => $payment->id],
                $user?->name);
            if (! empty($started['provider_id'])) $payment->update(['paystack_id' => $started['provider_id']]);
            return $started['url'];
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'meta' => array_merge($payment->meta ?? [], ['error' => $e->getMessage()])]);
            throw $e instanceof PaymentException ? $e : new PaymentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Apply a verified transaction (from API verify or a signed webhook). Idempotent: row-locked and a no-op once successful.
     * $tx must already be normalized: reference, status (success|failed|…), amount in kobo, currency, id, channel.
     */
    public function complete(array $tx): ?Payment
    {
        $ref = (string) ($tx['reference'] ?? '');
        if ($ref === '') return null;
        return DB::transaction(function () use ($ref, $tx) {
            $payment = Payment::where('reference', $ref)->lockForUpdate()->first();
            if (! $payment || $payment->status === 'success') return $payment;

            $ok = ($tx['status'] ?? null) === 'success'
                && (int) ($tx['amount'] ?? 0) >= $payment->amount_kobo
                && strtoupper((string) ($tx['currency'] ?? 'NGN')) === $payment->currency;
            if (! $ok) {
                if (in_array($tx['status'] ?? null, ['failed', 'abandoned', 'reversed', 'expired'], true) || ($tx['status'] ?? null) === 'success') {
                    $payment->update(['status' => 'failed', 'meta' => array_merge($payment->meta ?? [], ['provider_status' => $tx['status'] ?? null, 'amount' => $tx['amount'] ?? null])]);
                    if (($tx['status'] ?? null) === 'success') Log::warning('redstar pay: amount/currency mismatch', ['ref' => $ref]);
                }
                return $payment;
            }
            $payment->update(['status' => 'success', 'paystack_id' => (string) ($tx['id'] ?? $payment->paystack_id ?? ''),
                'channel' => $tx['channel'] ?? $payment->channel, 'paid_at' => now()]);
            $payable = $payment->payable;
            if ($payable instanceof Payable) $payable->markPaid($payment);
            return $payment;
        });
    }
}
