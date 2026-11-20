<?php
namespace App\Jobs;

use App\Ai\Ai;
use App\Ai\SemanticIndex;
use App\Models\Edition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** After the PDF worker marks an edition ready: chunk + embed, then queue summaries and OCR cleanup. */
class IndexEditionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;
    public array $backoff = [60, 300];

    public function __construct(public int $editionId, public bool $followUps = true) {}

    public function uniqueId(): string { return (string) $this->editionId; }

    public function handle(Ai $ai, SemanticIndex $index): void
    {
        $edition = Edition::find($this->editionId);
        if (! $edition || $edition->processing_status !== 'ready') return;
        $index->chunk($edition);
        if ($ai->available('search') || $ai->available('ask')) {
            while ($index->embedPending($edition->id, 200) > 0);
        }
        $edition->forceFill(['ai_indexed_at' => now()])->save();

        if (! $this->followUps) return;
        if ($ai->available('summaries') && ! $edition->summaries()->exists()) SummarizeEditionJob::dispatch($edition->id);
        if ($ai->available('ocr')) {
            $edition->pages()->whereNull('cleaned_status')->get()->filter->needsCleanup()
                ->each(fn ($p) => CleanOcrPageJob::dispatch($p->id));
        }
    }
}
