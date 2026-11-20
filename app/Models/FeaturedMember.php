<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** "Old Boy of the month". */
class FeaturedMember extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['month' => 'date'];
    public function user() { return $this->belongsTo(User::class); }

    public static function current(): ?self
    {
        return static::with('user')->whereDate('month', '<=', now()->toDateString())->latest('month')->first();
    }
}
