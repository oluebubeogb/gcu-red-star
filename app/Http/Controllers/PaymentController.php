<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Payments\Gateways;
use App\Payments\MonnifyGateway;
use App\Payments\Payable;
use App\Payments\Payments;
use App\Payments\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Provider return (verify via API) and webhooks (HMAC-SHA512). Both fulfil through Payments::complete (idempotent). */
class PaymentController extends Controller
{
    public function callback(Request $request, Gateways $gateways, Payments $payments)
    {
        $ref = (string) $request->query('paymentReference', $request->query('reference', $request->query('trxref', '')));
        $txRef = (string) $request->query('transactionReference', '');
        $payment = $ref !== '' ? Payment::where('reference', $ref)->first() : null;
        if (! $payment && $txRef !== '') $payment = Payment::where('paystack_id', $txRef)->first();
        abort_unless($payment, 404);
        if ($payment->status !== 'success') {
            try {
                $gateway = $gateways->get($payment->gateway ?: config('commerce.method'));
                $payment = $payments->complete($gateway->verify($payment->reference, $payment->paystack_id ?: ($txRef ?: null))) ?? $payment;
            } catch (\Throwable $e) {
                Log::warning('redstar pay: verify failed', ['ref' => $payment->reference, 'error' => $e->getMessage()]);
            }
        }
        $back = $payment->payable instanceof Payable ? $payment->payable->returnUrl() : route('home');
        return $payment->status === 'success'
            ? redirect($back)->with('status', 'Payment received — thank you! (ref '.$payment->reference.')')
            : redirect($back)->withErrors(['payment' => 'We could not confirm that payment yet. If you were charged it will be confirmed automatically shortly (ref '.$payment->reference.').']);
    }

    public function webhook(Request $request, PaystackGateway $paystack, Payments $payments)
    {
        $payload = $request->getContent();
        if (! $paystack->validSignature($payload, $request->header('x-paystack-signature'))) {
            return response()->json(['message' => 'invalid signature'], 401);
        }
        $event = json_decode($payload, true) ?: [];
        if (($event['event'] ?? null) === 'charge.success' && is_array($event['data'] ?? null)) {
            $payments->complete($event['data']); // Paystack webhook amounts are already kobo
        }
        return response()->json(['ok' => true]);
    }

    public function webhookMonnify(Request $request, MonnifyGateway $monnify, Payments $payments)
    {
        $payload = $request->getContent();
        $signature = $request->header('monnify-signature');
        $signed = $monnify->validSignature($payload, $signature);
        if (! $signed) {
            // Official docs: monnify-signature is omitted on sandbox notifications. Live always requires it.
            // In sandbox, never trust the body — confirm via the API before fulfilling.
            if ($signature || ! $monnify->testMode()) {
                return response()->json(['message' => 'invalid signature'], 401);
            }
        }
        $event = json_decode($payload, true) ?: [];
        if (($event['eventType'] ?? null) === 'SUCCESSFUL_TRANSACTION' && is_array($event['eventData'] ?? null)) {
            $data = $event['eventData'];
            if ($signed) {
                $payments->complete($monnify->normalize($data));
            } else {
                try {
                    $payments->complete($monnify->verify((string) ($data['paymentReference'] ?? ''), $data['transactionReference'] ?? null));
                } catch (\Throwable $e) {
                    Log::warning('redstar pay: monnify sandbox webhook verify failed', ['error' => $e->getMessage()]);
                }
            }
        }
        return response()->json(['ok' => true]);
    }
}
