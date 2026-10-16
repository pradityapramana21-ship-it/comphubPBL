<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tingkat keparahan temuan satu aturan moderasi.
 *
 * Berbeda dari skor: skor menjawab "berapa banyak", severity menjawab
 * "seberapa serius". Admin memakai severity untuk memutuskan urutan
 * penanganan; sistem memakai skor untuk ambang keputusan.
 */
enum RuleSeverity: string
{
    /** Petunjuk saja. Kegiatan mungkin belum matang, tapi bukan masalah. */
    case Info = 'info';

    /** Tanda ringan, tetap perlu dilihat manusia. */
    case Low = 'low';

    /** Pola mencurigakan; jangan lolos tanpa diperiksa. */
    case Medium = 'medium';

    /** Pelanggaran aturan eksplisit; pengajuan ditahan paksa. */
    case High = 'high';

    /** Pelanggaran keras. Skor sendirinya sudah melewati ambang tolak. */
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informasi',
            self::Low => 'Ringan',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    /** Nama token warna dari design system, bukan nilai warna langsung. */
    public function tone(): string
    {
        return match ($this) {
            self::Info, self::Low => 'neutral',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Low => 2,
            self::Medium => 3,
            self::High => 4,
            self::Critical => 5,
        };
    }

    /**
     * Urutan dari yang paling ringan. Dipakai admin untuk mengurutkan
     * temuan mana yang dibaca lebih dulu.
     */
    public function rank(): int
    {
        return self::cases() !== [] ? array_search($this, self::cases(), true) : 0;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
