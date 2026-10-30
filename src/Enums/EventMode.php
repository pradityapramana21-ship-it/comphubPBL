<?php

declare(strict_types=1);

namespace App\Enums;

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

    public function requiresLocation(): bool
    {
        return $this !== self::Online;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
