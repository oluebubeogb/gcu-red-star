<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['meta' => 'array', 'paid_at' => 'datetime'];

    public function payable() { return $this->morphTo(); }
    public function user() { return $this->belongsTo(User::class); }
    public function scopeSuccessful($q) { return $q->where('status', 'success'); }
}
