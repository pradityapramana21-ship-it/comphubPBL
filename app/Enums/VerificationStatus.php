<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status verifikasi organisasi penyedia.
 *
 * Penting: hanya organisasi yang sudah `Verified` boleh mengajukan kegiatan.
 * Verifikasi bersifat opsional — sebuah organisasi bisa tetap dikenal
 * lewat email resmi dan situs web, bukti dokumen hanya diminta bila admin
 * menganggap perlu (Panduan §7.2).
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Verifikasi Ditolak',
        };
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Pending => [
                'Mengisi dan memperbarui data organisasi',
                'Menyimpan draft kegiatan',
            ],
            self::Verified => [
                'Mengisi dan memperbarui data organisasi',
                'Menyimpan draft kegiatan',
                'Mengajukan kegiatan untuk ditinjau',
            ],
            self::Rejected => [
                'Mengisi dan memperbarui data organisasi',
                'Menyimpan draft kegiatan',
                'Melavored alasan penolakan untuk diperbaiki',
            ],
        };
    }

    public function canSubmitEvents(): bool
    {
        return $this === self::Verified;
    }

    /**
     * Nama token warna dari design system.
     *
     * Ada di sini, bukan ditulis terpisah di Blade, supaya satu lencana
     * yang sama bisa dipakai untuk status kegiatan maupun status
     * verifikasi organisasi.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
