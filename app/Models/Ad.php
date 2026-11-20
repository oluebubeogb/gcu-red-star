<?php
namespace App\Models;

use App\Payments\Payable;
use App\Storage\MagazineStorage;
use Illuminate\Database\Eloquent\Model;

/** Ads and congratulatory/obituary notices. Live = paid + approved + within its dates. */
class Ad extends Model implements Payable
{
    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'paid_at' => 'datetime'];

    public function scopeLive($q)
    {
        return $q->where('status', 'approved')->whereNotNull('paid_at')
            ->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today());
    }

    public function markPaid(Payment $payment): void
    {
        $this->forceFill(['status' => $this->status === 'approved' ? 'approved' : 'paid', 'paid_at' => now()])->save();
    }

    public function returnUrl(): string { return route('advertise'); }

    public function card(): array
    {
        return $this->only(['id', 'kind', 'advertiser', 'title', 'body', 'slot_key']) + [
            'image' => $this->image_path ? app(MagazineStorage::class)->url($this->image_path) : null,
            'url' => $this->link_url ? route('ads.click', $this->id) : null,
        ];
    }
}
