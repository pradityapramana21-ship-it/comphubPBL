<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReason: string
{
    case MisleadingInfo = 'misleading_info';
    case BrokenLink = 'broken_link';
    case Spam = 'spam';
    case Scam = 'scam';
    case Duplicate = 'duplicate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MisleadingInfo => 'Informasi menyesatkan',
            self::BrokenLink => 'Tautan rusak',
            self::Spam => 'Spam',
            self::Scam => 'Penipuan',
            self::Duplicate => 'Duplikat kegiatan',
            self::Other => 'Lainnya',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MisleadingInfo => 'Tanggal, biaya, atau syarat peserta tidak sesuai kenyataan.',
            self::BrokenLink => 'Tautan pendaftaran tidak bisa dibuka atau sudah kedaluwarsa.',
            self::Spam => 'Konten tidak relevan, repetitif, atau mencurigakan.',
            self::Scam => 'Ada permintaan uang atau data pribadi yang tidak wajar.',
            self::Duplicate => 'Kegiatan ini sama persis dengan yang sudah ada.',
            self::Other => 'Masalah lain yang tidak termasuk kategori di atas.',
        };
    }

    public function isUrgent(): bool
    {
        return $this === self::Scam;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
