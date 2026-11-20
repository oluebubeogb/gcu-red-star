<?php
namespace App\Http\Controllers;

use App\Ai\Ai;
use App\Ai\AskArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/** Ask the archive — open to everyone (guests see public editions only). The only AI call made inside a web request. */
class AskController extends Controller
{
    public function show(Request $request, Ai $ai)
    {
        return Inertia::render('Public/Ask', ['q' => (string) $request->query('q'), 'available' => $ai->available('ask'),
            'scope' => $request->user()?->hasVerifiedEmail() ? 'members' : 'public']);
    }

    public function ask(Request $request, Ai $ai, AskArchive $ask)
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:5', 'max:500']]);
        if (! $ai->available('ask')) {
            return response()->json(['message' => 'Ask the archive is not available right now. Please try the search instead.'], 503);
        }
        try {
            return response()->json($ask->answer($data['question'], $request->user(), $request->ip()));
        } catch (\Throwable $e) {
            Log::warning('redstar ai: ask failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'The archive assistant is busy right now. Please try again in a minute, or use the search.'], 503);
        }
    }
}
