<?php
namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\PageTag;
use App\Models\User;
use App\Support\Badges;
use App\Support\Homecoming;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Sets (year of admission), houses, public member profiles and Homecoming. Directory listings respect show_in_directory. */
class CommunityController extends Controller
{
    private function tagRows($tags): array
    {
        return $tags->map(fn (PageTag $t) => [
            'id' => $t->id, 'page' => $t->page_number, 'note' => $t->note,
            'name' => $t->user?->name ?? $t->name, 'username' => $t->user?->show_in_directory ? $t->user->username : null,
            'edition' => $t->edition->only(['title', 'slug', 'year']),
        ])->values()->all();
    }

    /** Tags of the given (public) members on published editions. */
    private function tagsFor($userIds, int $limit = 30): array
    {
        return $this->tagRows(PageTag::visible()->with(['edition', 'user'])->whereIn('user_id', $userIds)->latest()->limit($limit)->get());
    }

    private function news(array $where): array
    {
        return Announcement::live()->where($where)->latest('published_at')->limit(10)->get(['id', 'title', 'body', 'published_at'])->toArray();
    }

    public function sets()
    {
        $sets = User::directory()->whereNotNull('admission_year')
            ->selectRaw('admission_year, count(*) as members')->groupBy('admission_year')->orderByDesc('admission_year')
            ->pluck('members', 'admission_year');
        return Inertia::render('Community/Sets', ['sets' => $sets, 'jubileeSet' => Homecoming::upcomingSet()]);
    }

    public function set(int $year)
    {
        abort_unless($year >= 1929 && $year <= now()->year, 404);
        $members = User::directory()->where('admission_year', $year)->orderBy('name')->get();
        return Inertia::render('Community/Set', [
            'year' => $year,
            'members' => $members->map->card()->values(),
            'tags' => $this->tagsFor($members->pluck('id')),
            'news' => $this->news(['set_year' => $year]),
            'isJubilee' => $year === Homecoming::upcomingSet(),
            'jubileeYear' => $year + Homecoming::YEARS,
        ]);
    }

    public function houses()
    {
        $counts = User::directory()->whereNotNull('house')->selectRaw('house, count(*) as c')->groupBy('house')->pluck('c', 'house');
        return Inertia::render('Community/Houses', [
            'houses' => collect(Roles::HOUSES)->map(fn ($h) => ['name' => $h, 'slug' => Roles::houseSlug($h), 'members' => $counts[$h] ?? 0]),
        ]);
    }

    public function house(string $slug)
    {
        $house = Roles::houseFromSlug($slug) ?? abort(404);
        $members = User::directory()->where('house', $house)->orderByDesc('admission_year')->orderBy('name')->get();
        return Inertia::render('Community/House', [
            'house' => $house,
            'members' => $members->map->card()->values(),
            'tags' => $this->tagsFor($members->pluck('id')),
            'news' => $this->news(['house' => $house]),
        ]);
    }

    public function member(Request $request, User $user)
    {
        abort_unless($user->profileVisibleTo($request->user()), 404);
        return Inertia::render('Community/Member', [
            'member' => $user->card() + ['graduation_year' => $user->graduation_year, 'house_slug' => $user->house ? Roles::houseSlug($user->house) : null],
            'contributions' => Badges::for($user),
            'tags' => $this->tagRows(PageTag::visible()->with(['edition', 'user'])->where('user_id', $user->id)->latest()->limit(50)->get()),
            'isMe' => $request->user()?->id === $user->id,
        ]);
    }

    public function homecoming()
    {
        $set = Homecoming::upcomingSet();
        $event = Homecoming::upcomingEvent();
        return Inertia::render('Community/Homecoming', [
            'set' => $set,
            'year' => $set + Homecoming::YEARS,
            'event' => $event?->card(),
            'sponsors' => \App\Models\Sponsorship::credited()->where('target', 'homecoming')->where('homecoming_year', $set + Homecoming::YEARS)
                ->with('package:id,name')->get()->map->credit(),
            'members' => User::directory()->where('admission_year', $set)->orderBy('name')->limit(200)->get()->map->card()->values(),
            'past' => Event::published()->where('kind', 'homecoming')->orderByDesc('set_year')->get()
                ->filter->isPast()->map->card()->values(),
        ]);
    }
}
