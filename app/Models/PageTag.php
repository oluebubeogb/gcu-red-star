<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageTag extends Model
{
    protected $guarded = ['id'];
    public function edition() { return $this->belongsTo(Edition::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function tagger() { return $this->belongsTo(User::class, 'tagged_by'); }

    /** Tags on published editions only. */
    public function scopeVisible($q)
    {
        return $q->whereHas('edition', fn ($e) => $e->published());
    }
}
