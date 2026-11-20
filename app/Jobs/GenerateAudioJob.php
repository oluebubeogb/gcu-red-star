<?php
namespace App\Jobs;

use App\Ai\Ai;
use App\Models\EditionAudio;
use App\Storage\MagazineStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

/** Text-to-speech for one page's Comfort Reading text; MP3 stored via MagazineStorage. Editor-triggered only. */
class GenerateAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(public int $audioId) {}

    public function handle(Ai $ai, MagazineStorage $storage): void
    {
        $audio = EditionAudio::find($this->audioId);
        if (! $audio) return;
        try {
            if (! $ai->available('audio')) throw new \RuntimeException('Audio editions are switched off.');
            $page = \App\Models\EditionPage::where('edition_id', $audio->edition_id)->where('page_number', $audio->page_number)->first();
            $blocks = \App\Models\EditionBlock::where('edition_id', $audio->edition_id)->where('page_number', $audio->page_number)->orderBy('position')->pluck('text');
            $text = $page && $page->cleaned_status === 'approved' ? $page->cleaned_text : $blocks->implode("\n\n");
            $text = trim($text ?: (string) $page?->text);
            if ($text === '') throw new \RuntimeException('No text on this page.');
            $url = $ai->speak($text);
            $res = Http::timeout(120)->get($url);
            if ($res->failed()) throw new \RuntimeException('Could not download generated audio.');
            $key = "editions/{$audio->edition_id}/audio/{$audio->page_number}.mp3";
            $storage->put($key, $res->body());
            $audio->update(['audio_key' => $key, 'status' => 'ready', 'error' => null, 'chars' => mb_strlen($text)]);
        } catch (\Throwable $e) {
            $audio->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
