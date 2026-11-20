<?php
namespace App\Console\Commands;

use App\Ai\Ai;
use App\Jobs\IndexEditionJob;
use App\Models\Edition;
use App\Models\EditionChunk;
use Illuminate\Console\Command;

/** Picks up editions the PDF worker has just marked ready (and any unembedded chunks) and queues AI indexing. */
class AiSweepCommand extends Command
{
    protected $signature = 'redstar:ai-sweep';
    protected $description = 'Queue AI indexing for newly processed editions and unembedded chunks';

    public function handle(Ai $ai): int
    {
        if (! $ai->configured()) { $this->line('AI not configured — skipping.'); return self::SUCCESS; }
        $ids = Edition::where('processing_status', 'ready')
            ->where(fn ($q) => $q->whereNull('ai_indexed_at')->orWhereColumn('processed_at', '>', 'ai_indexed_at'))
            ->limit(5)->pluck('id');
        $unembedded = ($ai->available('search') || $ai->available('ask'))
            ? EditionChunk::whereNull('embedded_at')->distinct()->limit(5)->pluck('edition_id') : collect();
        foreach ($ids->merge($unembedded)->unique() as $id) IndexEditionJob::dispatch($id, $ids->contains($id));
        $this->info('Queued '.$ids->merge($unembedded)->unique()->count().' editions.');
        return self::SUCCESS;
    }
}
