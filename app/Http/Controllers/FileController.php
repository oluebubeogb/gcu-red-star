<?php
namespace App\Http\Controllers;

use App\Storage\MagazineStorage;
use Illuminate\Http\Request;

/** Streams private magazine files from the local disk via signed URLs. */
class FileController extends Controller
{
    public function show(Request $request, MagazineStorage $storage, string $key)
    {
        abort_if(str_contains($key, '..'), 404);
        abort_unless($storage->exists($key), 404);
        $stream = $storage->readStream($key);
        $mime = match (strtolower(pathinfo($key, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', default => 'application/octet-stream',
        };
        // Signed URLs carry the file version (`v`), so images can be cached hard; PDFs stay short.
        $cache = str_starts_with($mime, 'image/') || str_starts_with($mime, 'audio/') ? 'private, max-age=604800, immutable' : 'private, max-age=3600';
        return response()->stream(function () use ($stream) { fpassthru($stream); }, 200, [
            'Content-Type' => $mime, 'Cache-Control' => $cache, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
