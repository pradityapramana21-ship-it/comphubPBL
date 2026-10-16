<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status penanganan laporan kegiatan oleh admin.
 *
 * Terpisah dari ReportReason: `reason_code` menjawab "apa masalahnya",
 * enum ini menjawab "sudah ditangani sampai mana".
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Baru',
            self::Reviewing => 'Sedang Ditinjau',
            self::Resolved => 'Selesai Ditangani',
            self::Dismissed => 'Tidak Diteruskan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Reviewing => 'warning',
            self::Resolved => 'success',
            self::Dismissed => 'neutral',
        };
    }

    /**
     * Laporan yang masih harus dikerjakan admin.
     */
    public function isPending(): bool
    {
        return in_array($this, [self::Open, self::Reviewing], true);
    }

    /**
     * Status tempat laporan harus berada sebelum ditandai selesai.
     * Admin tidak boleh menutup laporan dari status `open` tanpa sempat
     * admin melihatnya — ini yang membuat jejak penanganan selalu terbaca.
     *
     * Mengembalikan NILAI string, bukan enum case — supaya bisa langsung
     * dibandingkan dengan $next->value.
     *
     * @return list<string>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Reviewing->value, self::Resolved->value, self::Dismissed->value],
            self::Reviewing => [self::Resolved->value, self::Dismissed->value],
            self::Resolved => [self::Reviewing->value],
            self::Dismissed => [self::Reviewing->value],
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
