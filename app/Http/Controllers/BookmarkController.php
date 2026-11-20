<?php
namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Edition;
use App\Storage\MagazineStorage;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Verified members: bookmarks (edition + page) and signed PDF downloads. */
class BookmarkController extends Controller
{
    public function index(Request $request)
    {
        $bookmarks = $request->user()->bookmarks()->with('edition:id,title,slug,year,status,published_at')->latest()->get()
            ->filter(fn ($b) => $b->edition?->isPublished())
            ->map(fn ($b) => ['id' => $b->id, 'page_number' => $b->page_number, 'note' => $b->note,
                'edition' => $b->edition->only(['title', 'slug', 'year']), 'created_at' => $b->created_at])->values();
        return Inertia::render('Member/Bookmarks', ['bookmarks' => $bookmarks]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'page_number' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        abort_unless(Edition::findOrFail($data['edition_id'])->isPublished(), 404);
        $request->user()->bookmarks()->updateOrCreate(
            ['edition_id' => $data['edition_id'], 'page_number' => $data['page_number']],
            ['note' => $data['note'] ?? null],
        );
        return back()->with('status', 'Page bookmarked.');
    }

    public function destroy(Request $request, Bookmark $bookmark)
    {
        abort_unless($bookmark->user_id === $request->user()->id, 403);
        $bookmark->delete();
        return back()->with('status', 'Bookmark removed.');
    }

    public function download(Edition $edition, MagazineStorage $storage)
    {
        abort_unless($edition->isPublished() && $edition->pdf_path, 404);
        return redirect()->away($storage->temporaryUrl($edition->pdf_path, now()->addMinutes(15)));
    }
}
