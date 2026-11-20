<?php
namespace App\Ai;

use App\Models\AskLog;
use App\Models\Edition;
use App\Models\EditionChunk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Retrieval-augmented answers from the archive only, with citations to edition + page.
 * Guests see public editions only; verified members also see members-only editions.
 */
class AskArchive
{
    public const NOT_FOUND = "I couldn't find that in the archive.";
    private const STOP = ['what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how', 'the', 'and', 'for', 'was', 'were',
        'are', 'is', 'did', 'does', 'about', 'with', 'from', 'that', 'this', 'there', 'their', 'have', 'has', 'had', 'tell', 'any', 'in', 'of'];

    public function __construct(private Ai $ai, private SemanticIndex $index) {}

    public static function scope(?User $user): Builder
    {
        return Edition::published()->when(! $user?->hasVerifiedEmail(), fn ($q) => $q->where('access', 'public'));
    }

    public static function normalize(string $q): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtolower(preg_replace('/[^\pL\pN\s]/u', ' ', $q)))), 0, 300);
    }

    /** @return array{answer: string, found: bool, citations: array, cached: bool} */
    public function answer(string $question, ?User $user, ?string $ip = null): array
    {
        $norm = self::normalize($question);
        $scopeKey = $user?->hasVerifiedEmail() ? 'members' : 'public';
        $key = 'ai:ask:'.sha1($scopeKey.'|'.config('ai.models.ask').'|'.$norm);
        $cached = Cache::get($key);
        $result = $cached ?? $this->generate($question, $user);
        if (! $cached) Cache::put($key, $result, now()->addHours(config('ai.cache_hours')));

        AskLog::create(['question' => mb_substr($question, 0, 1000), 'normalized' => $norm, 'user_id' => $user?->id,
            'ip_hash' => $ip ? hash('sha256', $ip.config('app.key')) : null, 'answered' => $result['found'], 'cached' => (bool) $cached]);
        return $result + ['cached' => (bool) $cached];
    }

    private function generate(string $question, ?User $user): array
    {
        $chunks = $this->retrieve($question, self::scope($user));
        if ($chunks->isEmpty()) return ['answer' => self::NOT_FOUND, 'found' => false, 'citations' => []];

        $sources = $chunks->values()->map(fn ($c, $i) => '['.($i + 1)."] {$c->edition->title}".($c->edition->year ? " ({$c->edition->year})" : '')
            .", page {$c->page_number}:\n".mb_substr($c->text, 0, 1500))->implode("\n\n");
        $reply = $this->ai->complete(
            "Sources from The Red Star archive:\n\n$sources\n\nQuestion: $question\n\nAnswer in 1-4 short paragraphs using ONLY the sources. Cite every claim with its source number in square brackets, e.g. [1]. If the sources do not answer the question, reply exactly: NOT_FOUND",
            'You answer questions about The Red Star, the magazine of Government College Umuahia, Nigeria. You must not use outside knowledge. If unsure, reply NOT_FOUND.',
            600, config('ai.models.ask'), 0.1);

        preg_match_all('/\[(\d{1,2})\]/', $reply, $m);
        $used = collect($m[1])->map(fn ($n) => (int) $n)->unique()->filter(fn ($n) => $n >= 1 && $n <= $chunks->count())->values();
        if (str_contains($reply, 'NOT_FOUND') || $used->isEmpty()) return ['answer' => self::NOT_FOUND, 'found' => false, 'citations' => []];

        $list = $chunks->values();
        return [
            'answer' => trim(preg_replace('/\[(\d{1,2})\]/', '[$1]', $reply)),
            'found' => true,
            'citations' => $used->map(fn ($n) => [
                'n' => $n, 'title' => $list[$n - 1]->edition->title, 'year' => $list[$n - 1]->edition->year,
                'page' => $list[$n - 1]->page_number,
                'url' => route('editions.show', ['edition' => $list[$n - 1]->edition->slug, 'page' => $list[$n - 1]->page_number]),
                'quote' => mb_substr(preg_replace('/\s+/u', ' ', $list[$n - 1]->text), 0, 220),
            ])->all(),
        ];
    }

    /** Hybrid retrieval: lexical (FTS / keyword) ∪ semantic, fused with reciprocal-rank fusion. */
    public function retrieve(string $question, Builder $scope, int $k = 6)
    {
        $editionIds = (clone $scope)->pluck('id');
        if ($editionIds->isEmpty()) return collect();
        $words = collect(preg_split('/\s+/u', self::normalize($question)))->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true))->values();

        $lexical = collect();
        if ($words->isNotEmpty()) {
            $q = EditionChunk::whereIn('edition_id', $editionIds);
            if (DB::getDriverName() === 'pgsql') {
                $tsq = $words->map(fn ($w) => preg_replace('/[^\pL\pN]/u', '', $w))->filter()->implode(' | ');
                $q->whereRaw("to_tsvector('english', text) @@ to_tsquery('english', ?)", [$tsq])
                    ->orderByRaw("ts_rank(to_tsvector('english', text), to_tsquery('english', ?)) desc", [$tsq]);
            } else {
                $q->where(fn ($w) => $words->each(fn ($word) => $w->orWhere('text', 'like', "%$word%")));
            }
            $lexical = $q->limit(30)->pluck('id');
        }

        $semantic = [];
        if ($this->ai->available('search') || $this->ai->available('ask')) {
            if ($vec = $this->index->queryVector($question)) {
                $semantic = array_keys($this->index->nearest($vec, (clone $scope), 30, Ai::pgvector() ? [] : $lexical->all()));
            }
        }

        $scores = [];
        foreach ($lexical->values() as $r => $id) $scores[$id] = ($scores[$id] ?? 0) + 1 / (60 + $r);
        foreach (array_values($semantic) as $r => $id) $scores[$id] = ($scores[$id] ?? 0) + 1 / (60 + $r);
        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $k);
        return EditionChunk::with('edition:id,title,slug,year')->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids))->values();
    }
}
