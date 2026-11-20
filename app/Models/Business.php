<?php
namespace App\Models;

use App\Payments\Payable;
use App\Storage\MagazineStorage;
use Illuminate\Database\Eloquent\Model;

class Business extends Model implements Payable
{
    protected $guarded = ['id'];
    protected $casts = ['featured_until' => 'datetime'];

    public function getRouteKeyName(): string { return 'slug'; }
    public function user() { return $this->belongsTo(User::class); }
    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function isFeatured(): bool { return $this->featured_until && $this->featured_until->isFuture(); }

    /** Featured months bought are stored on the payment meta; extends from the later of now / current expiry. */
    public function markPaid(Payment $payment): void
    {
        $months = max(1, (int) ($payment->meta['months'] ?? 1));
        $from = $this->isFeatured() ? $this->featured_until : now();
        $this->forceFill(['featured_until' => $from->copy()->addMonthsNoOverflow($months)])->save();
    }

    public function returnUrl(): string { return route('businesses.mine'); }

    public function card(): array
    {
        $owner = $this->user;
        return $this->only(['id', 'name', 'slug', 'category', 'description', 'state', 'location', 'phone', 'whatsapp', 'website']) + [
            'logo' => $this->logo_path ? app(MagazineStorage::class)->url($this->logo_path) : null,
            'featured' => $this->isFeatured(),
            'owner' => $owner ? ['name' => $owner->name, 'username' => $owner->show_in_directory ? $owner->username : null,
                'admission_year' => $owner->admission_year] : null,
        ];
    }
}
