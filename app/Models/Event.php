<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'published' => 'boolean'];

    public function scopePublished($q) { return $q->where('published', true); }

    public function isPast(): bool { return ($this->ends_on ?? $this->starts_on)?->endOfDay()->isPast() ?? false; }

    public function card(): array
    {
        return $this->only(['id', 'title', 'slug', 'kind', 'set_year', 'venue', 'description', 'published']) + [
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'cover_url' => $this->cover_path ? app(\App\Storage\MagazineStorage::class)->url($this->cover_path) : null,
            'is_past' => $this->isPast(),
        ];
    }
}
