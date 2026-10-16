<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tindakan pada antrean moderasi. Nilai ini ditulis ke tabel
 * `moderation_logs` sehingga admin selalu bisa melihat kenapa sebuah
 * kegiatan ditahan (Panduan FR-08: "alasan dan pelaku keputusan tercatat").
 *
 * `Submit`, `AutoRejected`, `AutoFlagged`, dan `Expired` dicatat otomatis
 * oleh sistem; sisanya tindakan manual admin.
 */
enum ModerationAction: string
{
    case Submit = 'submit';
    case AutoFlagged = 'auto_flagged';
    case AutoRejected = 'auto_rejected';
    case Approve = 'approve';
    case Reject = 'reject';
    case RequestChanges = 'request_changes';
    case Archive = 'archive';
    case Expire = 'expire';
    case Unarchive = 'unarchive';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Diajukan',
            self::AutoFlagged => 'Ditandai Otomatis',
            self::AutoRejected => 'Ditolak Otomatis',
            self::Approve => 'Disetujui',
            self::Reject => 'Ditolak',
            self::RequestChanges => 'Diminta Perbaikan',
            self::Archive => 'Diarsipkan',
            self::Expire => 'Kedaluwarsa',
            self::Unarchive => 'Dibuka Kembali',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Submit => 'Penyedia mengajukan kegiatan; sistem menjalankan seluruh aturan moderasi.',
            self::AutoFlagged => 'Skor risiko berada di ambang yang memerlukan pemeriksaan admin lebih dulu.',
            self::AutoRejected => 'Skor risiko melewati batas dan melanggar aturan eksplisit.',
            self::Approve => 'Administrator menyetujui; kegiatan mulai tampil untuk publik.',
            self::Reject => 'Administrator menolak dengan alasan yang tercatat.',
            self::RequestChanges => 'Administrator meminta perbaikan; penyedia dapat mengajukan ulang.',
            self::Archive => 'Administrator menyembunyikan kegiatan tanpa menghapusnya.',
            self::Expire => 'Sistem menandai kegiatan karena tenggat pendaftarannya lewat.',
            self::Unarchive => 'Administrator mengembalikan kegiatan dari arsip.',
        };
    }

    /**
     * True bila tindakan hanya boleh dilakukan oleh admin.
     */
    public function requiresAdmin(): bool
    {
        return match ($this) {
            self::Submit, self::AutoFlagged, self::AutoRejected, self::Expire => false,
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $a): string => $a->value, self::cases());
    }
}
