<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['published' => 'boolean', 'published_at' => 'datetime'];

    public function scopeLive($q)
    {
        return $q->where('published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
