<?php
namespace App\Storage;

/**
 * Storage abstraction for magazine files (PDFs, covers, page renders).
 * Code stores *keys* (e.g. "editions/1987/original.pdf"), never disk paths,
 * so moving from the VPS disk to S3-compatible storage is a config change.
 */
interface MagazineStorage
{
    public function put(string $key, $contents, array $options = []): bool;
    public function putFile(string $directory, \Illuminate\Http\UploadedFile $file, ?string $name = null): string;
    public function get(string $key): ?string;
    public function exists(string $key): bool;
    public function delete(string $key): bool;
    public function url(string $key): string;
    public function temporaryUrl(string $key, \DateTimeInterface $expires): string;
    public function readStream(string $key);
    public function diskName(): string;
}
