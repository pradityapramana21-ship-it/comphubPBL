<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status operasional sebuah akun. Terpisah dari UserRole: peran menentukan
 * "apa yang boleh", status menentukan "apakah boleh masuk sama sekali".
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Active => 'Akun dapat digunakan seperti biasa.',
            self::Suspended => 'Akun tidak dapat masuk. Hubungi administrator bila ini keliru.',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
