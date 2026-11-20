<?php
namespace App\Http\Controllers;

use App\Models\Edition;
use App\Storage\MagazineStorage;
use Illuminate\Http\Request;
use App\Support\ArchiveSearch;
use Inertia\Inertia;

class PublicController extends Controller
{
    private function card(Edition $e): array
    {
        return [
            'id' => $e->id, 'title' => $e->title, 'slug' => $e->slug, 'year' => $e->year,
            'volume' => $e->volume, 'issue' => $e->issue, 'summary' => $e->summary,
            'cover_url' => $e->coverUrl(), 'access' => $e->access,
            'published_at' => optional($e->published_at)->toDateString(),
        ];
    }

    public function home()
    {
        $latest = Edition::published()->latest('published_at')->take(4)->get()->map(fn ($e) => $this->card($e));
        $set = \App\Support\Homecoming::upcomingSet();
        $feature = \App\Models\FeaturedMember::current();
        return Inertia::render('Public/Home', [
            'latest' => $latest,
            'homecoming' => ['set' => $set, 'year' => $set + \App\Support\Homecoming::YEARS, 'event' => \App\Support\Homecoming::upcomingEvent()?->card()],
            'feature' => $feature ? ['blurb' => $feature->blurb, 'month' => $feature->month->format('F Y'),
                'member' => $feature->user->card()] : null,
            'banner' => \App\Support\Ads::pick('homepage_banner', request()->user())[0] ?? null,
            'notices' => \App\Support\Ads::pick('notice', request()->user(), 4),
            'announcements' => \App\Models\Announcement::live()->whereNull('set_year')->whereNull('house')->latest('published_at')->limit(3)->get(['id', 'title', 'body', 'published_at']),
            'stats' => [
                'editions' => Edition::published()->count(),
                'decades' => Edition::published()->whereNotNull('year')->selectRaw('count(distinct (year / 10)) as c')->value('c') ?? 0,
            ],
        ]);
    }

    public function editions()
    {
        return Inertia::render('Public/Editions', [
            'editions' => Edition::published()->latest('published_at')->paginate(12)->through(fn ($e) => $this->card($e)),
        ]);
    }

    public function edition(Request $request, Edition $edition)
    {
        abort_unless($edition->isPublished(), 404);
        $user = $request->user();
        $canRead = $edition->readableBy($user);
        $storage = app(MagazineStorage::class);
        $expires = now()->addHours(2);

        return Inertia::render('Public/Edition', [
            'edition' => $this->card($edition) + ['page_count' => $edition->page_count],
            'canRead' => $canRead,
            'canDownload' => (bool) ($user?->hasVerifiedEmail() && $edition->pdf_path),
            'startPage' => max(1, (int) $request->query('page', 1)),
            'pages' => $canRead ? $edition->pages()->get(['page_number', 'image_key', 'width', 'height'])
                ->map(fn ($p) => [
                    'n' => $p->page_number, 'width' => $p->width, 'height' => $p->height,
                    'image_url' => $p->image_key ? $storage->temporaryUrl($p->image_key, $expires) : null,
                ]) : [],
            'blocks' => $canRead ? $this->comfortBlocks($edition) : (object) [],
            'summary' => $canRead ? $this->summaries($edition) : null,
            'audio' => $canRead && app(\App\Ai\Ai::class)->configured() ? $edition->audio()->where('status', 'ready')->get()
                ->mapWithKeys(fn ($a) => [$a->page_number => $storage->temporaryUrl($a->audio_key, $expires)]) : (object) [],
            'bookmarks' => $user ? $user->bookmarks()->where('edition_id', $edition->id)->get(['id', 'page_number']) : [],
            'shareUrl' => route('editions.show', $edition->slug),
            'sponsors' => $edition->sponsorships()->credited()->with('package:id,name')->get()->map->credit(),
            'sidebarAd' => \App\Support\Ads::pick('edition_sidebar', $user)[0] ?? null,
            'print' => $edition->print_available && $edition->print_price_kobo ? ['price_kobo' => $edition->print_price_kobo, 'stock' => $edition->print_stock] : null,
            'tags' => $canRead ? \App\Models\PageTag::with('user:id,name,username,show_in_directory')->where('edition_id', $edition->id)->get()
                ->map(fn ($t) => [
                    'id' => $t->id, 'page' => $t->page_number, 'note' => $t->note,
                    'name' => $t->user?->name ?? $t->name,
                    'username' => $t->user?->show_in_directory ? $t->user->username : null,
                    'claimable' => ! $t->user_id,
                    'can_remove' => $user && ($user->isEditor() || $t->user_id === $user->id || (! $t->user_id && $t->tagged_by === $user->id)),
                ]) : [],
        ])->withViewData(['meta' => [
            'title' => $edition->title.' — The Red Star',
            'description' => $edition->summary ?: 'Read '.$edition->title.($edition->year ? " ({$edition->year})" : '').' from The Red Star, the magazine of Government College Umuahia.',
            'image' => $edition->cover_path ? route('editions.cover', $edition->slug) : null,
            'url' => route('editions.show', $edition->slug),
            'type' => 'article',
        ]]);
    }

    /** Comfort Reading blocks; pages with approved AI OCR cleanup use the cleaned text instead. */
    private function comfortBlocks(Edition $edition)
    {
        $blocks = $edition->blocks()->get(['page_number', 'type', 'text'])->groupBy('page_number')
            ->map(fn ($g) => $g->map->only(['type', 'text'])->values());
        foreach ($edition->pages()->where('cleaned_status', 'approved')->get(['page_number', 'cleaned_text']) as $p) {
            $blocks[$p->page_number] = collect(preg_split('/\n\s*\n/u', trim((string) $p->cleaned_text), -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($t) => ['type' => 'paragraph', 'text' => trim($t)])->values();
        }
        return $blocks;
    }

    /** Approved AI summaries + entities (edition-level and per page range). */
    private function summaries(Edition $edition): ?array
    {
        $rows = $edition->summaries()->where('status', 'approved')->orderByRaw('page_from is not null')->orderBy('page_from')->get();
        if ($rows->isEmpty()) return null;
        $overall = $rows->firstWhere('page_from', null);
        return [
            'overall' => $overall?->summary,
            'entities' => $overall?->entities ?? $rows->pluck('entities')->reduce(fn ($c, $e) => array_merge_recursive($c, (array) $e), []),
            'sections' => $rows->whereNotNull('page_from')->map(fn ($r) => ['from' => $r->page_from, 'to' => $r->page_to, 'summary' => $r->summary])->values(),
        ];
    }

    /** Stable cover URL for link previews (WhatsApp/Facebook) → redirects to a fresh signed URL. */
    public function cover(Edition $edition)
    {
        abort_unless($edition->isPublished() && $edition->cover_path, 404);
        return redirect()->away(app(MagazineStorage::class)->url($edition->cover_path))->header('Cache-Control', 'public, max-age=3600');
    }

    public function search(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 200));
        $out = ['results' => null, 'didYouMean' => null, 'fuzzy' => false, 'editions' => []];
        if (mb_strlen($q) >= 2) {
            $out = ArchiveSearch::run($q);
            $ids = $out['results']->getCollection()->pluck('edition_id')->unique();
            $editions = Edition::whereIn('id', $ids)->get()->keyBy('id');
            $term = $out['didYouMean'] ?? $q;
            $out['results'] = $out['results']->through(function ($row) use ($editions, $term) {
                $e = $editions[$row->edition_id];
                return [
                    'edition' => ['title' => $e->title, 'slug' => $e->slug, 'year' => $e->year, 'access' => $e->access],
                    'page' => $row->page_number,
                    // Escape PDF text; only our own <mark> tags survive (rendered with v-html).
                    'snippet' => isset($row->snippet)
                        ? str_replace(['⟦', '⟧'], ['<mark>', '</mark>'], e($row->snippet))
                        : $this->snippet((string) $row->text, $term),
                ];
            });
        }
        return Inertia::render('Public/Search', ['q' => $q] + $out);
    }

    /** Escaped plain-text snippet with <mark> around the first match (non-Postgres fallback). */
    private function snippet(string $text, string $q): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $pos = mb_stripos($text, $q);
        $start = max(0, ($pos ?: 0) - 120);
        $chunk = e(mb_substr($text, $start, 260));
        return preg_replace('/'.preg_quote(e($q), '/').'/iu', '<mark>$0</mark>', $chunk, 1);
    }

    public function archive()
    {
        $decades = Edition::published()->whereNotNull('year')->get(['year'])
            ->groupBy(fn ($e) => intdiv($e->year, 10) * 10)->map->count()->sortKeysDesc();
        return Inertia::render('Public/Archive', ['decades' => $decades]);
    }

    public function about() { return Inertia::render('Public/About'); }
    public function contribute() { return Inertia::render('Public/Contribute'); }
}
