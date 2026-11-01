<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Edition extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['published_at' => 'datetime'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? app(\App\Storage\MagazineStorage::class)->url($this->cover_path) : null;
    }
}
