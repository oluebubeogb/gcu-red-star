<?php
namespace App\Support;

use App\Models\Edition;
use App\Models\EditionPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Archive search over published editions.
 * Postgres: full-text (tsvector) ranking → "did you mean" via pg_trgm on the search_words vocabulary →
 * trigram word-similarity on page text. Other drivers (SQLite tests): LIKE + PHP levenshtein suggestions.
 */
final class ArchiveSearch
{
    private static function pg(): bool { return DB::getDriverName() === 'pgsql'; }

    private static function base(): Builder
    {
        return EditionPage::query()->join('editions', 'editions.id', '=', 'edition_pages.edition_id')
            ->where('editions.status', 'published')->whereNotNull('editions.published_at')->where('editions.published_at', '<=', now())
            ->select('edition_pages.edition_id', 'edition_pages.page_number');
    }

    /** @return array{results: LengthAwarePaginator, didYouMean: ?string, fuzzy: bool, editions: array} */
    public static function run(string $q): array
    {
        $ai = app(\App\Ai\Ai::class);
        $hybrid = $ai->available('search') && \App\Models\EditionChunk::whereNotNull('embedded_at')->exists();
        $per = $hybrid ? 50 : 20;
        $results = self::fullText($q, $per, $hybrid);
        $didYouMean = null; $fuzzy = false;
        if ($results->total() === 0 && ($s = self::suggest($q)) && mb_strtolower($s) !== mb_strtolower($q)) {
            $alt = self::fullText($s, $per, $hybrid);
            if ($alt->total() > 0) { $results = $alt; $didYouMean = $s; }
        }
        if ($results->total() === 0 && self::pg()) {
            $results = self::trigram($q, $per, $hybrid);
            $fuzzy = $results->total() > 0;
        }
        $semantic = false;
        if ($hybrid) [$results, $semantic] = self::fuse($q, $results);
        return ['results' => $results, 'didYouMean' => $didYouMean, 'fuzzy' => $fuzzy, 'semantic' => $semantic, 'editions' => self::editions($q)];
    }

    /**
     * Hybrid ranking: reciprocal-rank fusion of lexical pages (FTS / fuzzy) and semantic pages (embeddings).
     * Falls back to the lexical list when the query embedding is unavailable.
     */
    private static function fuse(string $q, LengthAwarePaginator $lexical): array
    {
        $index = app(\App\Ai\SemanticIndex::class);
        $vec = $index->queryVector($q);
        $rows = collect($lexical->items());
        if (! $vec) return [self::paginate($rows), false];

        $candidates = \App\Ai\Ai::pgvector() ? [] : \App\Models\EditionChunk::query()
            ->where(fn ($w) => $rows->each(fn ($r) => $w->orWhere(fn ($x) => $x->where('edition_id', $r->edition_id)->where('page_number', $r->page_number))))
            ->limit(300)->pluck('id')->all();
        $near = $index->nearest($vec, Edition::published(), 50, $candidates);
        $chunks = \App\Models\EditionChunk::whereIn('id', array_keys($near))->get(['id', 'edition_id', 'page_number'])->keyBy('id');

        $scores = []; $byKey = []; $seen = [];
        foreach ($rows->values() as $rank => $r) {
            $k = $r->edition_id.':'.$r->page_number; $byKey[$k] = $r;
            $scores[$k] = ($scores[$k] ?? 0) + 1 / (60 + $rank);
        }
        $rank = 0;
        foreach (array_keys($near) as $id) {
            if (! ($c = $chunks[$id] ?? null)) continue;
            $k = $c->edition_id.':'.$c->page_number;
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $scores[$k] = ($scores[$k] ?? 0) + 1 / (60 + $rank++);
        }
        arsort($scores);
        $missing = array_diff(array_keys($scores), array_keys($byKey));
        if ($missing) {
            foreach (EditionPage::query()->where(fn ($w) => collect($missing)->each(function ($k) use ($w) {
                [$e, $p] = explode(':', $k); $w->orWhere(fn ($x) => $x->where('edition_id', $e)->where('page_number', $p));
            }))->get(['edition_id', 'page_number', 'text']) as $p) {
                $byKey[$p->edition_id.':'.$p->page_number] = (object) ['edition_id' => $p->edition_id, 'page_number' => $p->page_number, 'text' => $p->text];
            }
        }
        $fused = collect(array_keys($scores))->map(fn ($k) => $byKey[$k] ?? null)->filter()->values();
        return [self::paginate($fused), true];
    }

    private static function paginate($items): LengthAwarePaginator
    {
        $page = max(1, (int) request('page', 1));
        $items = $items->take(100);
        return (new LengthAwarePaginator($items->forPage($page, 20)->values(), $items->count(), 20, $page,
            ['path' => request()->url()]))->withQueryString();
    }

    private static function fullText(string $q, int $per = 20, bool $first = false): LengthAwarePaginator
    {
        $b = self::base();
        if (self::pg()) {
            $tsq = "websearch_to_tsquery('english', ?)";
            $b->whereRaw("edition_pages.search @@ $tsq", [$q])
                ->selectRaw("ts_headline('english', coalesce(CASE WHEN edition_pages.cleaned_status = 'approved' THEN edition_pages.cleaned_text END, edition_pages.text, ''), $tsq, 'MaxWords=35, MinWords=15, StartSel=⟦, StopSel=⟧') as snippet", [$q])
                ->orderByRaw("ts_rank(edition_pages.search, $tsq) desc", [$q]);
        } else {
            $b->where(fn ($w) => $w->where('edition_pages.text', 'like', '%'.$q.'%')
                ->orWhere(fn ($x) => $x->where('edition_pages.cleaned_status', 'approved')->where('edition_pages.cleaned_text', 'like', '%'.$q.'%')))
                ->addSelect('edition_pages.text');
        }
        return $first ? $b->paginate($per, ['*'], 'page', 1) : $b->paginate($per)->withQueryString();
    }

    /** Typo fallback: pages containing a word similar to the query (pg_trgm <% operator, GIN-indexed). */
    private static function trigram(string $q, int $per = 20, bool $first = false): LengthAwarePaginator
    {
        $b = self::base()->addSelect('edition_pages.text')
            ->whereRaw('? <% edition_pages.text', [$q])
            ->orderByRaw('word_similarity(?, edition_pages.text) desc', [$q]);
        return $first ? $b->paginate($per, ['*'], 'page', 1) : $b->paginate($per)->withQueryString();
    }

    /** "Did you mean": replace each unknown word with the closest word from published editions. */
    public static function suggest(string $q): ?string
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($q)), -1, PREG_SPLIT_NO_EMPTY);
        if (! $words || count($words) > 6) return null;
        $changed = false;
        foreach ($words as $i => $w) {
            if (mb_strlen($w) < 3 || ! preg_match('/^\pL+$/u', $w)) continue;
            $best = self::pg() ? self::pgClosest($w) : self::phpClosest($w);
            if ($best && $best !== $w) { $words[$i] = $best; $changed = true; }
        }
        return $changed ? implode(' ', $words) : null;
    }

    private static function pgClosest(string $w): ?string
    {
        try {
            if (DB::table('search_words')->where('word', $w)->exists()) return $w;
            return DB::table('search_words')->whereRaw('word % ?', [$w])
                ->orderByRaw('similarity(word, ?) desc', [$w])->orderByDesc('ndoc')->value('word');
        } catch (\Throwable $e) {
            Log::warning('redstar: search_words unavailable', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** Small-dataset fallback (SQLite/dev): levenshtein over words of published pages. */
    private static function phpClosest(string $w): ?string
    {
        $vocab = [];
        foreach (self::base()->addSelect('edition_pages.text')->limit(2000)->pluck('text') as $text) {
            foreach (preg_split('/[^\pL]+/u', mb_strtolower((string) $text), -1, PREG_SPLIT_NO_EMPTY) as $v) {
                if (mb_strlen($v) >= 3) $vocab[$v] = true;
            }
        }
        if (isset($vocab[$w])) return $w;
        $best = null; $bestD = max(1, intdiv(mb_strlen($w), 3)) + 1;
        foreach (array_keys($vocab) as $v) {
            $d = levenshtein($w, $v);
            if ($d < $bestD) { $best = $v; $bestD = $d; }
        }
        return $best;
    }

    /** Editions whose title matches (trigram similarity on Postgres). */
    private static function editions(string $q): array
    {
        $b = Edition::published()->limit(6);
        self::pg()
            ? $b->where(fn ($w) => $w->where('title', 'ilike', "%$q%")->orWhereRaw('title % ?', [$q]))->orderByRaw('similarity(title, ?) desc', [$q])
            : $b->where('title', 'like', "%$q%");
        return $b->get(['title', 'slug', 'year', 'access'])->toArray();
    }

    /** Rebuild the "did you mean" vocabulary (call after publish/unpublish/delete). */
    public static function refreshVocabulary(): void
    {
        if (! self::pg()) return;
        try {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY search_words');
        } catch (\Throwable $e) {
            Log::warning('redstar: could not refresh search_words', ['error' => $e->getMessage()]);
        }
    }
}
