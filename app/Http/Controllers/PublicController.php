<?php
namespace App\Http\Controllers;

use App\Models\Edition;
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
        return Inertia::render('Public/Home', [
            'latest' => $latest,
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

    public function edition(Edition $edition)
    {
        abort_unless($edition->status === 'published', 404);
        return Inertia::render('Public/Edition', ['edition' => $this->card($edition)]);
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
