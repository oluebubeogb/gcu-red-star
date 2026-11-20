<?php
namespace App\Jobs;

use App\Ai\Ai;
use App\Models\AiSummary;
use App\Models\Edition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Summaries + entities (people, places, events, years) per page cluster and for the whole edition. */
class SummarizeEditionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;

    private const SYSTEM = 'You are an archivist for The Red Star, the magazine of Government College Umuahia (GCU), Nigeria. Use only the text given. Never invent names, places or dates.';

    public function __construct(public int $editionId) {}

    public function handle(Ai $ai): void
    {
        $edition = Edition::find($this->editionId);
        if (! $edition || ! $ai->available('summaries')) return;
        $status = $ai->autoPublish() ? 'approved' : 'draft';
        $model = config('ai.models.llm');

        // Clusters: consecutive pages up to ~6000 characters (or 4 pages).
        $clusters = []; $cur = null;
        foreach ($edition->pages()->get() as $p) {
            $t = trim($p->effectiveText());
            if ($t === '') continue;
            if (! $cur || mb_strlen($cur['text']) + mb_strlen($t) > 6000 || $p->page_number - $cur['from'] >= 4) {
                if ($cur) $clusters[] = $cur;
                $cur = ['from' => $p->page_number, 'to' => $p->page_number, 'text' => ''];
            }
            $cur['to'] = $p->page_number;
            $cur['text'] .= "\n\n[Page {$p->page_number}]\n".mb_substr($t, 0, 6000);
        }
        if ($cur) $clusters[] = $cur;
        if (! $clusters) return;

        $edition->summaries()->where('status', '!=', 'approved')->delete();
        $parts = []; $all = ['people' => [], 'places' => [], 'events' => [], 'years' => []];
        foreach ($clusters as $c) {
            $j = $ai->completeJson(
                "Pages {$c['from']}–{$c['to']} of \"{$edition->title}\":\n{$c['text']}\n\nReturn JSON: {\"summary\": \"2-3 sentences\", \"people\": [], \"places\": [], \"events\": [], \"years\": []}",
                self::SYSTEM, 700);
            $entities = self::entities($j);
            foreach ($entities as $k => $v) $all[$k] = array_merge($all[$k], $v);
            $parts[] = "Pages {$c['from']}–{$c['to']}: ".($j['summary'] ?? '');
            AiSummary::create(['edition_id' => $edition->id, 'page_from' => $c['from'], 'page_to' => $c['to'],
                'summary' => trim((string) ($j['summary'] ?? '')), 'entities' => $entities, 'status' => $status, 'model' => $model]);
        }
        $overall = $ai->complete("Section summaries of the edition \"{$edition->title}\":\n".implode("\n", $parts)
            ."\n\nWrite a 3-4 sentence summary of the whole edition for readers.", self::SYSTEM, 300);
        AiSummary::create(['edition_id' => $edition->id, 'summary' => $overall, 'status' => $status, 'model' => $model,
            'entities' => array_map(fn ($v) => array_values(array_slice(array_unique($v), 0, 40)), $all)]);
    }

    public static function entities(array $j): array
    {
        $clean = fn ($v) => array_values(array_unique(array_filter(array_map(fn ($x) => trim(mb_substr((string) (is_array($x) ? ($x['name'] ?? '') : $x), 0, 100)), (array) $v))));
        return ['people' => $clean($j['people'] ?? []), 'places' => $clean($j['places'] ?? []),
            'events' => $clean($j['events'] ?? []), 'years' => $clean($j['years'] ?? [])];
    }
}
