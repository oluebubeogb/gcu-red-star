<?php
namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Storage\MagazineStorage;
use App\Support\ArchiveSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Edition workflow: draft (+PDF) -> pending -> processing -> ready|failed (worker) -> published.
 * `status` = editorial state (draft|published), `processing_status` = worker state.
 */
class EditionController extends Controller
{
    public function __construct(private MagazineStorage $storage) {}

    private function row(Edition $e): array
    {
        return $e->only(['id', 'title', 'slug', 'year', 'volume', 'issue', 'summary', 'access', 'status', 'processing_status',
            'processing_error', 'page_count', 'pdf_path']) + [
            'cover_url' => $e->coverUrl(),
            'published_at' => optional($e->published_at)->toDateString(),
            'processed_at' => optional($e->processed_at)->toDateTimeString(),
        ];
    }

    public function index()
    {
        return Inertia::render('Hq/Editions/Index', [
            'editions' => Edition::latest()->paginate(25)->through(fn ($e) => $this->row($e)),
        ]);
    }

    public function create() { return Inertia::render('Hq/Editions/Edit', ['edition' => null]); }

    public function edit(Edition $edition) { return Inertia::render('Hq/Editions/Edit', ['edition' => $this->row($edition)]); }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'year' => ['nullable', 'integer', 'min:1927', 'max:2100'],
            'volume' => ['nullable', 'string', 'max:40'],
            'issue' => ['nullable', 'string', 'max:40'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'access' => ['required', 'in:public,members'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.config('redstar.max_pdf_kb')],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $edition = Edition::create(collect($data)->except(['pdf', 'cover'])->all() + [
            'slug' => $this->uniqueSlug($data['title'], $data['year'] ?? null),
            'storage_disk' => $this->storage->diskName(),
            'status' => 'draft',
            'processing_status' => 'pending',
            'uploaded_by' => $request->user()->id,
        ]);
        $this->storeFiles($request, $edition);
        return redirect()->route('hq.editions.edit', $edition->id)->with('status', 'Edition created.');
    }

    public function update(Request $request, Edition $edition)
    {
        $data = $this->validated($request);
        $edition->update(collect($data)->except(['pdf', 'cover'])->all());
        $this->storeFiles($request, $edition);
        return back()->with('status', 'Edition saved.');
    }

    private function storeFiles(Request $request, Edition $edition): void
    {
        if ($request->hasFile('cover')) {
            $ext = strtolower($request->file('cover')->extension() ?: 'jpg');
            $old = $edition->cover_path;
            $key = $this->storage->putFile("editions/{$edition->id}", $request->file('cover'), "cover-upload-".time().".{$ext}");
            $edition->forceFill(['cover_path' => $key])->save();
            if ($old && $old !== $key) $this->storage->delete($old);
        }
        if ($request->hasFile('pdf')) {
            $key = $this->storage->putFile("editions/{$edition->id}", $request->file('pdf'), 'original.pdf');
            // New PDF: back to draft until the worker re-renders pages.
            $wasPublished = $edition->status === 'published';
            $edition->forceFill(['pdf_path' => $key, 'status' => 'draft', 'page_count' => null])->save();
            if ($wasPublished) ArchiveSearch::refreshVocabulary();
            $edition->queueProcessing();
        }
    }

    public function reprocess(Edition $edition)
    {
        abort_unless($edition->pdf_path, 422, 'Upload a PDF first.');
        $edition->queueProcessing();
        return back()->with('status', 'Queued for processing.');
    }

    public function publish(Request $request, Edition $edition)
    {
        if ($request->boolean('publish')) {
            if ($edition->processing_status !== 'ready') {
                return back()->withErrors(['publish' => 'The PDF must finish processing (status "ready") before publishing.']);
            }
            $edition->forceFill(['status' => 'published', 'published_at' => $edition->published_at ?? now()])->save();
            ArchiveSearch::refreshVocabulary();
            if (app(\App\Ai\Ai::class)->configured() && ! $edition->ai_indexed_at) \App\Jobs\IndexEditionJob::dispatch($edition->id);
            return back()->with('status', 'Edition published.');
        }
        $edition->forceFill(['status' => 'draft'])->save();
        ArchiveSearch::refreshVocabulary();
        return back()->with('status', 'Edition unpublished.');
    }

    public function destroy(Edition $edition)
    {
        if ($edition->status === 'published') {
            return back()->withErrors(['delete' => 'Unpublish the edition before deleting it.']);
        }
        $keys = $edition->pages()->pluck('image_key')->push($edition->pdf_path, $edition->cover_path)->filter()->unique();
        foreach ($keys as $key) $this->storage->delete($key);
        $edition->delete();
        return redirect()->route('hq.editions.index')->with('status', 'Edition deleted.');
    }

    private function uniqueSlug(string $title, ?int $year): string
    {
        $base = Str::slug(trim(($year ? "$year " : '').$title)) ?: 'edition';
        $slug = $base; $i = 2;
        while (Edition::where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }
}
