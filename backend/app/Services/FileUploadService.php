<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * FileUploadService — standar upload privat + signed URL (PRD 0.5, 0.7).
 * Semua foto (KTP, meter, rumah) disimpan di disk 'private'.
 * Akses via signed URL sementara.
 */
class FileUploadService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];

    private const MAX_SIZE_KB = 5120;

    private const SIGNED_EXPIRY_MINUTES = 30;

    public function upload(string $directory, UploadedFile $file): array
    {
        $this->assertSafeRelativePath($directory);

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Tipe file tidak diizinkan. Gunakan JPG, PNG, atau PDF.');
        }

        if ($file->getSize() > self::MAX_SIZE_KB * 1024) {
            throw new InvalidArgumentException('Ukuran file maksimal '.self::MAX_SIZE_KB / 1024 .' MB.');
        }

        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

        $path = $file->storeAs(
            $directory,
            $filename,
            ['disk' => 'private']
        );

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'url' => $this->signedUrl($path),
        ];
    }

    public function signedUrl(string $path, ?int $expiryMinutes = null): string
    {
        $expiry = $expiryMinutes ?? self::SIGNED_EXPIRY_MINUTES;

        return Storage::disk('private')->temporaryUrl(
            $path,
            now()->addMinutes($expiry)
        );
    }

    public function delete(string $path): bool
    {
        return Storage::disk('private')->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('private')->exists($path);
    }

    public function assertTenantPath(string $path, int $tenantId, string $purpose, bool $allowLegacyDocument = false): string
    {
        $this->assertSafeRelativePath($path);

        $prefix = $tenantId.'/'.$purpose.'/';
        if (str_starts_with($path, $prefix)) {
            return $path;
        }

        if ($allowLegacyDocument && str_starts_with($path, 'documents/')) {
            return $path;
        }

        throw new InvalidArgumentException('Referensi file tidak dimiliki tenant atau tidak sesuai tujuan upload.');
    }

    private function assertSafeRelativePath(string $path): void
    {
        if ($path === ''
            || trim($path) !== $path
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
            || in_array('..', explode('/', $path), true)
            || in_array('', explode('/', $path), true)) {
            throw new InvalidArgumentException('Path file privat tidak valid.');
        }
    }

    public static function maxSize(): int
    {
        return self::MAX_SIZE_KB;
    }

    public static function allowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'pdf'];
    }
}
