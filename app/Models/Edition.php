<?php
namespace App\Models;

use App\Storage\MagazineStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Edition extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['published_at' => 'datetime', 'processed_at' => 'datetime', 'ai_indexed_at' => 'datetime',
        'print_available' => 'boolean', 'auto_publish' => 'boolean', 'issue_date' => 'date'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string { return 'slug'; }

    public function pages() { return $this->hasMany(EditionPage::class)->orderBy('page_number'); }
    public function chunks() { return $this->hasMany(EditionChunk::class); }
    public function summaries() { return $this->hasMany(AiSummary::class); }
    public function sponsorships() { return $this->hasMany(Sponsorship::class); }
    public function audio() { return $this->hasMany(EditionAudio::class); }
    public function blocks() { return $this->hasMany(EditionBlock::class)->orderBy('page_number')->orderBy('position'); }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at && $this->published_at->lte(now());
    }

    /** Public editions are open to all; members-only editions need a verified account. */
    public function readableBy(?User $user): bool
    {
        return $this->access === 'public' || ($user && $user->hasVerifiedEmail());
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? app(MagazineStorage::class)->url($this->cover_path) : null;
    }

    /** Mark for (re)processing and notify the Python worker. The worker also polls the DB as a fallback. */
    public function queueProcessing(): void
    {
        $this->forceFill(['processing_status' => 'pending', 'processing_error' => null])->save();
        $key = config('redstar.pdf_queue');
        if (! $key) return;
        try {
            Redis::rpush($key, json_encode(['edition_id' => $this->id]));
        } catch (\Throwable $e) {
            Log::warning('redstar: could not push to PDF queue, worker DB poll will pick it up', ['edition' => $this->id, 'error' => $e->getMessage()]);
        }
    }
}
