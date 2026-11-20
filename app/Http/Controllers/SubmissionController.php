<?php
namespace App\Http\Controllers;

use App\Storage\MagazineStorage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Member/Submissions', [
            'submissions' => $request->user()->submissions()->latest()->get(['id', 'title', 'kind', 'status', 'created_at']),
        ]);
    }

    public function store(Request $request, MagazineStorage $storage)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'kind' => ['required', 'in:article,poem,photo,letter,news'],
            'body' => ['required_unless:kind,photo', 'nullable', 'string', 'max:30000'],
            'photo' => ['required_if:kind,photo', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
        unset($data['photo']);
        if ($data['kind'] === 'photo' && $request->hasFile('photo')) {
            $data['photo_path'] = $storage->putFile('submissions/'.$request->user()->id, $request->file('photo'));
        }
        $submission = $request->user()->submissions()->create($data);
        if (app(\App\Ai\Ai::class)->available('assistant')) \App\Jobs\ReviewSubmissionJob::dispatch($submission->id);
        return back()->with('status', 'Submission received — the editorial team will review it.');
    }
}
