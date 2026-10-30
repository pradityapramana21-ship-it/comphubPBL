<?php

declare(strict_types=1);

namespace App\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public static function notFound(string $message = 'Halaman tidak ditemukan.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'Anda tidak berhak mengakses halaman ini.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Silakan masuk terlebih dahulu.'): self
    {
        return new self(401, $message);
    }

    public static function methodNotAllowed(): self
    {
        return new self(405, 'Metode permintaan tidak didukung.');
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Permintaan tidak valid.',
            403 => 'Anda tidak berhak mengakses halaman ini.',
            404 => 'Halaman tidak ditemukan.',
            405 => 'Metode permintaan tidak didukung.',
            419 => 'Sesi formulir kedaluwarsa. Muat ulang halaman.',
            429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
            default => 'Terjadi kesalahan pada server.',
        };
    }
}
