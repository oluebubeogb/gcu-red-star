<?php
namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Mail\MonthlyDigest;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\FeaturedMember;
use App\Models\User;
use App\Storage\MagazineStorage;
use App\Support\Digest;
use App\Support\Homecoming;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** HQ: homecoming events, announcements (incl. set/house news), Old Boy of the month, digest test. */
class CommunityController extends Controller
{
    public function index()
    {
        return Inertia::render('Hq/Community', [
            'events' => Event::orderByDesc('set_year')->get()->map->card(),
            'announcements' => Announcement::latest()->limit(50)->get(),
            'features' => FeaturedMember::with('user:id,name,username')->latest('month')->limit(12)->get()
                ->map(fn ($f) => ['id' => $f->id, 'month' => $f->month->format('Y-m'), 'blurb' => $f->blurb, 'user' => $f->user]),
            'houses' => Roles::HOUSES,
            'jubileeSet' => Homecoming::upcomingSet(),
            'digestRecipients' => Digest::recipients()->count(),
        ]);
    }

    private function eventData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'set_year' => ['nullable', 'integer', 'min:1929', 'max:2100'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'venue' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'published' => ['boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    private function saveCover(Request $request, Event $event, MagazineStorage $storage): void
    {
        if (! $request->hasFile('cover')) return;
        $old = $event->cover_path;
        $event->update(['cover_path' => $storage->putFile("events/{$event->id}", $request->file('cover'))]);
        if ($old) $storage->delete($old);
    }

    public function storeEvent(Request $request, MagazineStorage $storage)
    {
        $data = $this->eventData($request);
        $base = Str::slug($data['title']) ?: 'event'; $slug = $base; $i = 2;
        while (Event::where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        $event = Event::create(collect($data)->except('cover')->all() + ['slug' => $slug, 'kind' => 'homecoming']);
        $this->saveCover($request, $event, $storage);
        return back()->with('status', 'Event created.');
    }

    public function updateEvent(Request $request, Event $event, MagazineStorage $storage)
    {
        $event->update(collect($this->eventData($request))->except('cover')->all());
        $this->saveCover($request, $event, $storage);
        return back()->with('status', 'Event saved.');
    }

    public function destroyEvent(Event $event, MagazineStorage $storage)
    {
        if ($event->cover_path) $storage->delete($event->cover_path);
        $event->delete();
        return back()->with('status', 'Event deleted.');
    }

    private function announcementData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'set_year' => ['nullable', 'integer', 'min:1929', 'max:2100'],
            'house' => ['nullable', Rule::in(Roles::HOUSES)],
            'published' => ['boolean'],
        ]);
        return $data;
    }

    public function storeAnnouncement(Request $request)
    {
        $data = $this->announcementData($request);
        Announcement::create($data + ['user_id' => $request->user()->id, 'published_at' => ($data['published'] ?? true) ? now() : null]);
        return back()->with('status', 'Announcement saved.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement)
    {
        $data = $this->announcementData($request);
        if (($data['published'] ?? false) && ! $announcement->published_at) $data['published_at'] = now();
        $announcement->update($data);
        return back()->with('status', 'Announcement saved.');
    }

    public function destroyAnnouncement(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('status', 'Announcement deleted.');
    }

    public function storeFeature(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', Rule::exists('users', 'username')],
            'month' => ['required', 'date_format:Y-m'],
            'blurb' => ['required', 'string', 'max:3000'],
        ]);
        $month = $data['month'].'-01';
        (FeaturedMember::whereDate('month', $month)->first() ?? new FeaturedMember(['month' => $month]))->fill([
            'user_id' => User::where('username', $data['username'])->value('id'), 'blurb' => $data['blurb'], 'picked_by' => $request->user()->id,
        ])->save();
        return back()->with('status', 'Old Boy of the month saved.');
    }

    public function destroyFeature(FeaturedMember $feature)
    {
        $feature->delete();
        return back()->with('status', 'Feature removed.');
    }

    public function testDigest(Request $request)
    {
        Mail::to($request->user())->send(new MonthlyDigest($request->user(), Digest::content()));
        return back()->with('status', 'Test digest sent to '.$request->user()->email.'.');
    }
}
