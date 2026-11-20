<?php
namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
use App\Support\DemoData;
use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Hq/Index', [
            'counts' => [
                'members' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'editions' => Edition::count(),
                'submissions' => Submission::where('status', 'submitted')->count(),
            ],
            'byCategory' => User::selectRaw('category, count(*) as c')->groupBy('category')->pluck('c', 'category'),
            'submissions' => Submission::with('user:id,name,username,category,is_demo')->latest()->take(20)->get()
                ->map(fn ($s) => $s->toArray() + ['photo_url' => $s->photo_path ? app(\App\Storage\MagazineStorage::class)->url($s->photo_path) : null]),
            'demo' => ['allowed' => \App\Support\DemoData::allowed(), 'count' => \App\Support\DemoData::count()],
            'editionStatus' => Edition::selectRaw('processing_status, count(*) as c')->groupBy('processing_status')->pluck('c', 'processing_status'),
        ]);
    }

    public function members(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $q = trim((string) $request->query('q'));
        $category = (string) $request->query('category');
        $demo = (string) $request->query('demo'); // '' | 'only' | 'hide'
        $house = (string) $request->query('house');
        return Inertia::render('Hq/Members', [
            'q' => $q,
            'filters' => ['category' => $category, 'demo' => $demo, 'house' => $house],
            'houses' => Roles::HOUSES,
            'categories' => Roles::CATEGORIES,
            'roles' => Roles::ROLES,
            'members' => User::query()
                ->when(isset(Roles::CATEGORIES[$category]), fn ($b) => $b->where('category', $category))
                ->when(in_array($house, Roles::HOUSES, true), fn ($b) => $b->where('house', $house))
                ->when($demo === 'only', fn ($b) => $b->where('is_demo', true))
                ->when($demo === 'hide', fn ($b) => $b->where('is_demo', false))
                ->when($q, fn ($b) => $b->where(fn ($w) => $w->where('name', 'ilike', "%$q%")->orWhere('email', 'ilike', "%$q%")->orWhere('username', 'ilike', "%$q%")))
                ->latest()->paginate(25)->withQueryString()
                ->through(fn ($u) => $u->only(['id', 'name', 'username', 'email', 'category', 'role', 'graduation_year', 'admission_year', 'house', 'current_class', 'email_verified_at', 'is_admin', 'is_demo'])),
        ]);
    }

    public function updateMember(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(Roles::CATEGORIES))],
            'role' => ['required', 'in:'.implode(',', Roles::ROLES)],
        ]);
        $user->forceFill($data)->save();
        return back()->with('status', "Updated {$user->name}.");
    }

    public function seedDemo(Request $request)
    {
        abort_unless($request->user()->isAdmin() && DemoData::allowed(), 403);
        $count = (int) $request->validate(['count' => ['required', 'integer', 'min:1', 'max:25']])['count'];
        DemoData::seed($count);
        return back()->with('status', "Seeded {$count} demo members with sample submissions and bookmarks.");
    }

    public function purgeDemo(Request $request)
    {
        abort_unless($request->user()->isAdmin() && DemoData::allowed(), 403);
        $n = DemoData::purge();
        return back()->with('status', "Removed {$n} demo members and their demo records.");
    }

    public function updateSubmission(Request $request, Submission $submission)
    {
        $data = $request->validate(['status' => ['required', 'in:submitted,in_review,accepted,declined']]);
        $submission->update($data);
        return back()->with('status', 'Submission updated.');
    }
}
