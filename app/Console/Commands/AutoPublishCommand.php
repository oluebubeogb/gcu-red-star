<?php
namespace App\Console\Commands;

use App\Models\Edition;
use App\Support\ArchiveSearch;
use Illuminate\Console\Command;

/** Publishes bulk-imported editions flagged auto_publish once the PDF worker has finished them. */
class AutoPublishCommand extends Command
{
    protected $signature = 'redstar:auto-publish';
    protected $description = 'Publish auto-publish editions whose processing is ready';

    public function handle(): int
    {
        $ready = Edition::where('auto_publish', true)->where('status', 'draft')->where('processing_status', 'ready')->get();
        foreach ($ready as $e) {
            $e->forceFill(['status' => 'published', 'published_at' => $e->published_at ?? now(), 'auto_publish' => false])->save();
            if (app(\App\Ai\Ai::class)->configured() && ! $e->ai_indexed_at) \App\Jobs\IndexEditionJob::dispatch($e->id);
        }
        if ($ready->isNotEmpty()) ArchiveSearch::refreshVocabulary();
        $this->info("Published {$ready->count()} editions.");
        return self::SUCCESS;
    }
}
