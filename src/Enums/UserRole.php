<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Provider = 'provider';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Pengguna',
            self::Provider => 'Penyedia Kegiatan',
            self::Admin => 'Administrator',
        };
    }

    public function middleware(): string
    {
        return 'role:' . $this->value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
