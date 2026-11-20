<?php
namespace App\Http\Controllers;

use App\Models\Edition;
use App\Models\PageTag;
use App\Models\User;
use App\Notifications\TaggedOnPage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** People tagging on edition pages (verified members). */
class TagController extends Controller
{
    public function store(Request $request, Edition $edition)
    {
        abort_unless($edition->isPublished() && $edition->readableBy($request->user()), 404);
        $data = $request->validate([
            'page_number' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) $edition->page_count)],
            'username' => ['nullable', 'string', 'max:40', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $target = null;
        if (! empty($data['username'])) {
            $target = User::where('username', strtolower(ltrim($data['username'], '@')))->first()
                ?? throw ValidationException::withMessages(['username' => 'No member with that username. Use a name instead.']);
        }
        $attrs = ['edition_id' => $edition->id, 'page_number' => $data['page_number'],
            'user_id' => $target?->id, 'name' => $target ? null : trim($data['name'])];
        if (PageTag::where($attrs)->exists()) return back()->with('status', 'Already tagged on this page.');

        $tag = PageTag::create($attrs + ['note' => $data['note'] ?? null, 'tagged_by' => $request->user()->id]);
        if ($target && $target->id !== $request->user()->id) {
            $target->notify(new TaggedOnPage($tag->load('edition', 'tagger')));
        }
        return back()->with('status', 'Tag added.');
    }

    /** "This is me": attach a free-text tag to my account. */
    public function claim(Request $request, PageTag $tag)
    {
        abort_if($tag->user_id, 422, 'This tag already belongs to a member.');
        $tag->update(['user_id' => $request->user()->id]);
        return back()->with('status', 'Tag claimed — it now shows on your profile.');
    }

    public function destroy(Request $request, PageTag $tag)
    {
        $u = $request->user();
        abort_unless($u->isEditor() || $tag->user_id === $u->id || ($tag->tagged_by === $u->id && ! $tag->user_id), 403);
        $tag->delete();
        return back()->with('status', 'Tag removed.');
    }
}
