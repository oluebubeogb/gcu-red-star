<?php
namespace App\Ai;

use App\Models\Edition;
use App\Models\EditionChunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Chunking, embeddings (pgvector or JSON fallback) and nearest-neighbour lookup. */
class SemanticIndex
{
    public function __construct(private Ai $ai) {}

    /** Re-chunk an edition from page text (approved OCR cleanup wins). Unchanged chunks keep their embedding. */
    public function chunk(Edition $edition): int
    {
        $max = config('ai.chunk_chars');
        $existing = $edition->chunks()->get()->keyBy(fn ($c) => $c->page_number.':'.$c->text_hash);
        $keep = [];
        foreach ($edition->pages()->get() as $page) {
            $paras = preg_split('/\n\s*\n/u', trim($page->effectiveText()), -1, PREG_SPLIT_NO_EMPTY);
            $chunks = []; $buf = '';
            foreach ($paras as $p) {
                $p = trim(preg_replace('/\s+/u', ' ', $p));
                if ($buf !== '' && mb_strlen($buf) + mb_strlen($p) > $max) { $chunks[] = $buf; $buf = ''; }
                $buf = trim("$buf\n\n$p");
                while (mb_strlen($buf) > $max * 1.5) { $chunks[] = mb_substr($buf, 0, $max); $buf = mb_substr($buf, $max - 150); }
            }
            if (mb_strlen($buf) >= 20) $chunks[] = $buf;
            foreach ($chunks as $i => $text) {
                $hash = sha1($text);
                $row = $existing->get($page->page_number.':'.$hash);
                if ($row) { $row->update(['chunk_index' => $i]); $keep[] = $row->id; continue; }
                $keep[] = EditionChunk::create(['edition_id' => $edition->id, 'page_number' => $page->page_number,
                    'chunk_index' => $i, 'text' => $text, 'text_hash' => $hash])->id;
            }
        }
        $edition->chunks()->whereNotIn('id', $keep)->delete();
        return count($keep);
    }

    /** Embed chunks that have no (current-model) embedding. Returns number embedded. */
    public function embedPending(?int $editionId = null, int $limit = 400): int
    {
        $model = config('ai.models.embed');
        $done = 0;
        EditionChunk::query()->when($editionId, fn ($q) => $q->where('edition_id', $editionId))
            ->where(fn ($q) => $q->whereNull('embedded_at')->orWhere('embed_model', '!=', $model))
            ->orderBy('id')->limit($limit)->get()->chunk(16)
            ->each(function ($batch) use ($model, &$done) {
                $vectors = $this->ai->embed($batch->pluck('text')->all());
                foreach ($batch->values() as $i => $chunk) $this->store($chunk, $vectors[$i], $model);
                $done += $batch->count();
            });
        return $done;
    }

    private function store(EditionChunk $chunk, array $vec, string $model): void
    {
        $chunk->forceFill(['embedding_json' => json_encode($vec), 'embed_model' => $model, 'embedded_at' => now()])->save();
        if (Ai::pgvector() && count($vec) !== (int) config('ai.embed_dims')) {
            // Model changed to a different size than the vector column: keep the JSON copy, skip the column.
            \Illuminate\Support\Facades\Log::warning('redstar ai: embedding has '.count($vec).' dims, column expects '.config('ai.embed_dims').' — set REDSTAR_AI_EMBED_DIMS and re-run the migration.');
        } elseif (Ai::pgvector()) {
            DB::update('update edition_chunks set embedding = ?::vector where id = ?', [self::literal($vec), $chunk->id]);
        }
    }

    public static function literal(array $v): string { return '['.implode(',', array_map(fn ($x) => round($x, 7), $v)).']'; }

    /** Query embedding (cached, short timeout). Null when AI is off or fails — callers fall back to lexical. */
    public function queryVector(string $q): ?array
    {
        $key = 'ai:qvec:'.sha1(config('ai.models.embed').'|'.mb_strtolower(trim($q)));
        if ($v = Cache::get($key)) return $v;
        try {
            config(['ai.timeout' => min(15, config('ai.timeout'))]);
            $v = $this->ai->embed([$q])[0];
            Cache::put($key, $v, now()->addHours(config('ai.cache_hours')));
            return $v;
        } catch (\Throwable $e) {
            Log::warning('redstar ai: query embedding failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Nearest chunks to $vec within $scope (Edition query). pgvector: ANN index. Fallback: PHP cosine over a bounded
     * candidate set ($candidateIds from FTS/trigram, else the most recent chunks in scope).
     * @return array<int, float> chunk_id => similarity, best first
     */
    public function nearest(array $vec, Builder $scope, int $limit = 30, array $candidateIds = []): array
    {
        $editionIds = (clone $scope)->pluck('id');
        if ($editionIds->isEmpty()) return [];
        $min = (float) config('ai.semantic_min');
        if (Ai::pgvector()) {
            $lit = self::literal($vec);
            return DB::table('edition_chunks')->whereIn('edition_id', $editionIds)->whereNotNull('embedding')
                ->selectRaw('id, 1 - (embedding <=> ?::vector) as score', [$lit])
                ->orderByRaw('embedding <=> ?::vector', [$lit])->limit($limit)->pluck('score', 'id')->map(fn ($s) => (float) $s)
                ->filter(fn ($s) => $s >= $min)->all();
        }
        $rows = EditionChunk::whereIn('edition_id', $editionIds)->whereNotNull('embedding_json')
            ->when($candidateIds, fn ($q) => $q->whereIn('id', $candidateIds), fn ($q) => $q->latest('id')->limit(600))
            ->get(['id', 'embedding_json']);
        $scores = [];
        foreach ($rows as $r) $scores[$r->id] = self::cosine($vec, json_decode($r->embedding_json, true) ?: []);
        $scores = array_filter($scores, fn ($s) => $s >= $min);
        arsort($scores);
        return array_slice($scores, 0, $limit, true);
    }

    public static function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) { $dot += $a[$i] * $b[$i]; $na += $a[$i] ** 2; $nb += $b[$i] ** 2; }
        return $na && $nb ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}
