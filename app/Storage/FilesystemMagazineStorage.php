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

    public function url(string $key): string
    {
        // Private local disk: serve through a signed app route; s3 disks use native URLs.
        if (config("filesystems.disks.{$this->disk}.driver") === 'local') {
            return URL::temporarySignedRoute('files.show', now()->addHours(6), ['key' => $key]);
        }
        return $this->fs()->url($key);
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expires): string
    {
        if (config("filesystems.disks.{$this->disk}.driver") === 'local') {
            return URL::temporarySignedRoute('files.show', $expires, ['key' => $key]);
        }
        return $this->fs()->temporaryUrl($key, $expires);
    }
}
