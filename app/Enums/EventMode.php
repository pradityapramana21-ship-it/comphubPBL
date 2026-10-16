<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Format penyelenggaraan kegiatan. Dipakai sebagai filter FR-04 dan untuk
 * menentukan apakah lokasi wajib diisi.
 */
enum EventMode: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Hybrid => 'Hybrid',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Online => 'globe',
            self::Offline => 'map-pin',
            self::Hybrid => 'combine',
        };
    }

    /**
     * Offline dan hybrid wajib punya lokasi. Ini ditegakkan dua lapis:
     * aturan ini di aplikasi, dan CHECK constraint di database.
     */
    public function requiresLocation(): bool
    {
        return $this !== self::Online;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $m): string => $m->value, self::cases());
    }
}
