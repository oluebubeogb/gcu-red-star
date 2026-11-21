<?php
namespace App\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Works for any Laravel disk: "magazines" (local, default) or an s3 disk later. */
class FilesystemMagazineStorage implements MagazineStorage
{
    public function __construct(private string $disk) {}

    private function fs(): Filesystem { return Storage::disk($this->disk); }

    public function put(string $key, $contents, array $options = []): bool { return (bool) $this->fs()->put($key, $contents, $options); }
    public function putFile(string $directory, UploadedFile $file, ?string $name = null): string
    {
        return $name ? $this->fs()->putFileAs($directory, $file, $name) : $this->fs()->putFile($directory, $file);
    }
    public function get(string $key): ?string { return $this->fs()->get($key); }
    public function exists(string $key): bool { return $this->fs()->exists($key); }
    public function delete(string $key): bool { return $this->fs()->delete($key); }
    public function readStream(string $key) { return $this->fs()->readStream($key); }
    public function diskName(): string { return $this->disk; }

    private function local(): bool { return config("filesystems.disks.{$this->disk}.driver") === 'local'; }

    /**
     * Cache-friendly signed URL: expiry is rounded UP to a 6-hour boundary and `v` (file mtime) is signed in,
     * so the same file gets the same URL for hours (browser/service-worker cache hits on slow connections)
     * and a new URL as soon as the file changes (e.g. a reprocessed page).
     */
    private function signed(string $key, \DateTimeInterface $expires): string
    {
        $window = 6 * 3600;
        $ts = (int) (ceil($expires->getTimestamp() / $window) * $window);
        $params = ['key' => $key];
        try { $params['v'] = $this->fs()->lastModified($key); } catch (\Throwable) {}
        // Relative so the signature does not include scheme/host — those change behind a reverse proxy.
        return URL::temporarySignedRoute('files.show', \Illuminate\Support\Carbon::createFromTimestamp($ts), $params, absolute: false);
    }

    public function url(string $key): string
    {
        // Private local disk: serve through a signed app route; s3 disks use native URLs.
        return $this->local() ? $this->signed($key, now()->addHours(6)) : $this->fs()->url($key);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expires): string
    {
        return $this->local() ? $this->signed($key, $expires) : $this->fs()->temporaryUrl($key, $expires);
    }
}
