<?php
namespace App\Models;

use App\Mail\PrintOrderMail;
use App\Payments\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PrintOrder extends Model implements Payable
{
    public const STATUSES = ['pending_payment', 'paid', 'printing', 'shipped', 'ready', 'delivered', 'cancelled'];

    protected $guarded = ['id'];
    protected $casts = ['paid_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function edition() { return $this->belongsTo(Edition::class); }

    /** Stock is decremented when payment succeeds (row-locked; never below zero — HQ sees a shortfall note). */
    public function markPaid(Payment $payment): void
    {
        $edition = Edition::whereKey($this->edition_id)->lockForUpdate()->first();
        $short = $edition->print_stock < $this->quantity;
        $edition->forceFill(['print_stock' => max(0, $edition->print_stock - $this->quantity)])->save();
        $this->forceFill(['status' => 'paid', 'paid_at' => now(),
            'tracking_note' => $short ? 'Stock shortfall at payment — reprint needed.' : $this->tracking_note])->save();
        $this->notify('Order confirmed');
    }

    public function returnUrl(): string { return route('orders.index'); }

    public function notify(string $headline): void
    {
        try {
            Mail::to($this->user->email)->queue(new PrintOrderMail($this->fresh(['edition', 'user']), $headline));
        } catch (\Throwable $e) {
            Log::warning('redstar: print order mail failed', ['order' => $this->id, 'error' => $e->getMessage()]);
        }
    }
}
