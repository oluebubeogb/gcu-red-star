<?php
namespace App\Http\Controllers;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'kind' => ['required', 'in:article,poem,photo,letter,news'],
            'body' => ['required', 'string', 'max:30000'],
        ]);
        $request->user()->submissions()->create($data);
        return back()->with('status', 'Submission received — the editorial team will review it.');
    }
}
