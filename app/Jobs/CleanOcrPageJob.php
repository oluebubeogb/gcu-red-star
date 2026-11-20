<?php
namespace App\Jobs;

use App\Ai\Ai;
use App\Models\EditionPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** LLM cleanup of OCR'd / garbled page text. Stored separately (cleaned_text) and only used after editor approval. */
class CleanOcrPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $pageId) {}

    public function handle(Ai $ai): void
    {
        $page = EditionPage::find($this->pageId);
        if (! $page || ! $ai->available('ocr') || trim((string) $page->text) === '') return;
        $cleaned = $ai->complete(
            "Fix OCR errors in this magazine page text: correct broken words, hyphenation and obvious character mistakes, restore paragraphs. Do NOT add, remove or rephrase content. Keep names as written. Output only the corrected text.\n\n---\n".mb_substr($page->text, 0, 7000),
            'You are a careful proofreader restoring scanned text from a Nigerian school magazine.',
            2500);
        $page->forceFill(['cleaned_text' => $cleaned, 'cleaned_status' => 'pending'])->save();
    }
}
