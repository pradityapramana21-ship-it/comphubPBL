<?php

declare(strict_types=1);

namespace App\Services;

final class UploadService
{
    private const LIMITS = [
        'poster' => 2 * 1024 * 1024,
        'guidebook' => 5 * 1024 * 1024,
    ];

    private const MIMES = [
        'poster' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        'guidebook' => [
            'application/pdf' => 'pdf',
        ],
    ];

    public function __construct(private readonly string $root)
    {
        if (! is_dir($root)) {
            mkdir($root, 0700, true);
        }
    }

    /** @param array<string, mixed> $file */
    public function store(array $file, string $kind): string
    {
        if (! isset(self::MIMES[$kind])) {
            throw new \InvalidArgumentException('Jenis unggahan tidak dikenal.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Berkas gagal diunggah.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmp === '' || ! is_file($tmp) || $size <= 0 || $size > self::LIMITS[$kind]) {
            throw new \InvalidArgumentException('Ukuran berkas tidak valid.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension = is_string($mime) ? (self::MIMES[$kind][$mime] ?? null) : null;

        if ($extension === null) {
            throw new \InvalidArgumentException('Tipe berkas tidak didukung.');
        }

        $directory = $kind === 'poster' ? 'posters' : 'guidebooks';
        $targetDirectory = rtrim($this->root, '/') . '/' . $directory;

        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0700, true);
        }

        $relative = $directory . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $target = rtrim($this->root, '/') . '/' . $relative;

        $moved = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $target) : rename($tmp, $target);

        if (! $moved) {
            throw new \RuntimeException('Berkas tidak dapat disimpan.');
        }

        chmod($target, 0600);

        return $relative;
    }

    public function remove(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }

        $path = $this->absolutePath($relative);

        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    public function absolutePath(string $relative): ?string
    {
        $root = realpath($this->root);
        $path = realpath(rtrim($this->root, '/') . '/' . ltrim($relative, '/'));

        if ($root === false || $path === false || ! str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    public function mime(string $path): string
    {
        return (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream');
    }
}
