<?php
namespace App\Http\Controllers\Hq;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Storage\MagazineStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Archive push: many PDFs at once (the browser sends them one request per file, so no giant uploads),
 * or a folder already on the magazines volume under imports/. Each file becomes a draft edition queued for the worker.
 */
class BulkUploadController extends Controller
{
    public function __construct(private MagazineStorage $storage) {}

    private function disk() { return Storage::disk($this->storage->diskName()); }

    public function index()
    {
        return Inertia::render('Hq/Bulk', [
            'folders' => collect($this->disk()->directories('imports'))->map(fn ($d) => [
                'path' => $d, 'pdfs' => collect($this->disk()->files($d))->filter(fn ($f) => str_ends_with(strtolower($f), '.pdf'))->count(),
            ])->values(),
            'editions' => Edition::whereNotNull('import_batch')->latest('id')->limit(300)
                ->get(['id', 'title', 'year', 'volume', 'issue', 'access', 'status', 'processing_status', 'processing_error', 'page_count', 'import_batch', 'auto_publish']),
            'maxKb' => config('redstar.max_pdf_kb'),
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'pdfs' => ['required', 'array', 'min:1', 'max:50'],
            'pdfs.*' => ['file', 'mimes:pdf', 'max:'.config('redstar.max_pdf_kb')],
            'rows' => ['nullable', 'array'], 'rows.*' => ['array'],
            'csv' => ['nullable', 'string', 'max:200000'],
            'batch' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'auto_publish' => ['boolean'], 'access' => ['nullable', 'in:public,members'],
        ]);
        $batch = $data['batch'] ?? now()->format('YmdHis');
        $csv = self::parseCsv($data['csv'] ?? '');
        $made = [];
        foreach ($request->file('pdfs') as $i => $file) {
            $name = $file->getClientOriginalName();
            $meta = $this->meta($name, ($data['rows'][$i] ?? []) + ($csv[strtolower($name)] ?? []), $data['access'] ?? 'public');
            $edition = $this->draft($meta, $batch, (bool) ($data['auto_publish'] ?? false), $request->user()->id);
            $edition->forceFill(['pdf_path' => $this->storage->putFile("editions/{$edition->id}", $file, 'original.pdf')])->save();
            $edition->queueProcessing();
            $made[] = $edition->id;
        }
        return $request->wantsJson() ? response()->json(['ids' => $made, 'batch' => $batch])
            : back()->with('status', count($made).' draft editions queued for processing.');
    }

    public function folder(Request $request)
    {
        $data = $request->validate([
            'folder' => ['required', 'string', 'max:200', 'regex:#^imports/[A-Za-z0-9 _\-/]+$#', 'not_regex:#\.\.#'],
            'csv' => ['nullable', 'string', 'max:200000'],
            'auto_publish' => ['boolean'], 'access' => ['nullable', 'in:public,members'],
        ]);
        $files = collect($this->disk()->files($data['folder']))->filter(fn ($f) => str_ends_with(strtolower($f), '.pdf'))->sort()->values();
        if ($files->isEmpty()) return back()->withErrors(['folder' => 'No PDF files found in that folder.']);
        $csv = self::parseCsv($data['csv'] ?? '');
        $batch = Str::slug(basename($data['folder'])).'-'.now()->format('His');
        foreach ($files as $path) {
            $meta = $this->meta(basename($path), $csv[strtolower(basename($path))] ?? [], $data['access'] ?? 'public');
            $edition = $this->draft($meta, $batch, (bool) ($data['auto_publish'] ?? false), $request->user()->id);
            $key = "editions/{$edition->id}/original.pdf";
            $this->disk()->move($path, $key); // moved (not copied) so large archives don't double disk use
            $edition->forceFill(['pdf_path' => $key])->save();
            $edition->queueProcessing();
        }
        return back()->with('status', $files->count().' PDFs from '.$data['folder'].' queued as draft editions.');
    }

    public function autoPublish(Request $request)
    {
        $d = $request->validate(['batch' => ['required', 'string'], 'auto_publish' => ['required', 'boolean']]);
        $n = Edition::where('import_batch', $d['batch'])->where('status', 'draft')->update(['auto_publish' => $d['auto_publish']]);
        return back()->with('status', "Auto-publish ".($d['auto_publish'] ? 'on' : 'off')." for {$n} editions.");
    }

    /** CSV with a header row: filename,title,date,volume,issue,members_only → keyed by lower-case filename. */
    public static function parseCsv(string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($csv)), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) return [];
        $head = array_map(fn ($h) => Str::snake(trim(strtolower($h))), str_getcsv(array_shift($lines)));
        $out = [];
        foreach ($lines as $l) {
            $row = array_combine($head, array_pad(array_slice(str_getcsv($l), 0, count($head)), count($head), ''));
            if (! empty($row['filename'])) $out[strtolower(trim($row['filename']))] = array_map('trim', $row);
        }
        return $out;
    }

    private function meta(string $filename, array $row, string $access): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $date = null;
        if (! empty($row['date'])) { try { $date = Carbon::parse($row['date']); } catch (\Throwable) {} }
        $year = $date?->year ?? (! empty($row['year']) ? (int) $row['year'] : (preg_match('/(19[2-9]\d|20\d\d)/', $base, $m) ? (int) $m[1] : null));
        $members = filter_var($row['members_only'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return [
            'title' => mb_substr(trim($row['title'] ?? '') ?: Str::headline(str_replace(['_', '-'], ' ', $base)), 0, 180),
            'year' => $year && $year >= 1927 && $year <= 2100 ? $year : null,
            'issue_date' => $date?->toDateString(),
            'volume' => mb_substr((string) ($row['volume'] ?? ''), 0, 40) ?: null,
            'issue' => mb_substr((string) ($row['issue'] ?? ''), 0, 40) ?: null,
            'access' => $members === null ? $access : ($members ? 'members' : 'public'),
        ];
    }

    private function draft(array $meta, string $batch, bool $auto, int $userId): Edition
    {
        $slug = Str::slug($meta['title'].($meta['year'] && ! str_contains($meta['title'], (string) $meta['year']) ? ' '.$meta['year'] : '')) ?: 'edition';
        $base = $slug; $i = 2;
        while (Edition::where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return Edition::create($meta + ['slug' => $slug, 'storage_disk' => $this->storage->diskName(), 'status' => 'draft',
            'processing_status' => 'pending', 'uploaded_by' => $userId, 'import_batch' => $batch, 'auto_publish' => $auto]);
    }
}
