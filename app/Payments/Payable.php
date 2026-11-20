<?php
namespace App\Payments;

use App\Models\Payment;

/** A model that can be paid for through Paystack. */
interface Payable
{
    /** Called exactly once, inside a transaction, when its payment succeeds. */
    public function markPaid(Payment $payment): void;

    /** Where to send the payer after the callback. */
    public function returnUrl(): string;
}
