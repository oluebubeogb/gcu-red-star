<?php
namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Submission;
use App\Models\User;
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
            'submissions' => Submission::with('user:id,name,username')->latest()->take(20)->get(),
        ]);
    }

    public function members(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $q = trim((string) $request->query('q'));
        return Inertia::render('Hq/Members', [
            'q' => $q,
            'categories' => Roles::CATEGORIES,
            'roles' => Roles::ROLES,
            'members' => User::query()
                ->when($q, fn ($b) => $b->where(fn ($w) => $w->where('name', 'ilike', "%$q%")->orWhere('email', 'ilike', "%$q%")->orWhere('username', 'ilike', "%$q%")))
                ->latest()->paginate(25)->withQueryString()
                ->through(fn ($u) => $u->only(['id', 'name', 'username', 'email', 'category', 'role', 'graduation_year', 'current_class', 'email_verified_at', 'is_admin'])),
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

    public function updateSubmission(Request $request, Submission $submission)
    {
        $data = $request->validate(['status' => ['required', 'in:submitted,in_review,accepted,declined']]);
        $submission->update($data);
        return back()->with('status', 'Submission updated.');
    }
}
