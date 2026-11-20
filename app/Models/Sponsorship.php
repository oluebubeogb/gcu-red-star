<?php
namespace App\Models;

use App\Payments\Payable;
use App\Storage\MagazineStorage;
use Illuminate\Database\Eloquent\Model;

/** Credited publicly only once paid AND an editor approved the name/logo (status = approved). */
class Sponsorship extends Model implements Payable
{
    protected $guarded = ['id'];
    protected $casts = ['paid_at' => 'datetime'];

    public function package() { return $this->belongsTo(SponsorshipPackage::class, 'package_id'); }
    public function edition() { return $this->belongsTo(Edition::class); }
    public function payments() { return $this->morphMany(Payment::class, 'payable'); }
    public function scopeCredited($q) { return $q->where('status', 'approved')->whereNotNull('paid_at'); }

    public function markPaid(Payment $payment): void
    {
        $this->forceFill(['status' => $this->status === 'approved' ? 'approved' : 'paid', 'paid_at' => now()])->save();
    }

    public function returnUrl(): string { return route('sponsor.index'); }

    public function credit(): array
    {
        return ['id' => $this->id, 'name' => $this->sponsor_name, 'type' => $this->sponsor_type, 'website' => $this->website,
            'package' => $this->package?->name, 'logo' => $this->logo_path ? app(MagazineStorage::class)->url($this->logo_path) : null];
    }
}
