<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status kegiatan. Alur utamanya:
 *
 *   draft ──submit──> pending ──approve──> approved ──tenggat lewat──> expired
 *                       │  ▲ request_changes │
 *                       │  │                 │ perbaikan
 *                       ▼  │ changes_requested
 *                       │                   reject
 *                       │                   ▼
 *                       └──────────────> rejected ──perbaiki──> (kembali pending)
 *
 * `archived` dipakai untuk menyembunyikan kegiatan yang sudah tayang atau
 * melanggar, tanpa menghapus jejaknya (Panduan §6.4: data yang sudah
 * dipublikasikan lebih aman diarsipkan daripada hard-delete).
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Pending => 'Menunggu Tinjauan',
            self::ChangesRequested => 'Perlu Diperbaiki',
            self::Rejected => 'Ditolak',
            self::Approved => 'Tayang',
            self::Expired => 'Kedaluwarsa',
            self::Archived => 'Diarsipkan',
        };
    }

    /**
     * Nama token warna dari design system UI/UX, bukan nilai warna langsung.
     * Dengan begitu frontend tinggal memakai `class="badge badge-{{ $status->tone() }}"`.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Pending => 'warning',
            self::ChangesRequested => 'warning',
            self::Rejected => 'danger',
            self::Approved => 'success',
            self::Expired => 'neutral',
            self::Archived => 'neutral',
        };
    }

    /**
     * Hanya status approved yang boleh muncul di daftar publik sebagai
     * kegiatan aktif (Panduan FR-03).
     */
    public function isVisibleToPublic(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Status yang boleh diubah provider atas namanya sendiri.
     */
    public function isEditableByProvider(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Rejected], true);
    }

    /**
     * Status yang sedang ditunggu moderator — inilah isi antrean review admin.
     */
    public function isInReviewQueue(): bool
    {
        return in_array($this, [self::Pending, self::ChangesRequested], true);
    }

    /**
     * Status terminal: setelah sampai ke sini, kegiatan tidak akan kembali
     * ke alur tinjauan kecuali diarsipkan/dibuka manual oleh admin.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Archived], true);
    }

    /**
     * Transisi status yang sah. Dipakai oleh EventService untuk menolak
     * perubahan status yang tidak wajar, dan diuji di Feature/Admin.
     *
     * Mengembalikan NILAI string, bukan enum case — supaya bisa langsung
     * dibandingkan dengan $next->value.
     *
     * @return list<string>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending->value, self::Archived->value],
            self::Pending => [self::Approved->value, self::Rejected->value, self::ChangesRequested->value, self::Draft->value],
            self::ChangesRequested => [self::Pending->value, self::Archived->value],
            self::Rejected => [self::Pending->value, self::Archived->value],
            self::Approved => [self::Expired->value, self::Archived->value],
            self::Expired => [self::Archived->value],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
