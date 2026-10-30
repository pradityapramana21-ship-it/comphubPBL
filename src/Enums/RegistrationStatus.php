<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\EventSchedule;

enum RegistrationStatus: string
{
    case Upcoming = 'upcoming';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Akan dibuka',
            self::Open => 'Pendaftaran dibuka',
            self::Closed => 'Pendaftaran ditutup',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Upcoming => 'status-soon',
            self::Open => 'status-open',
            self::Closed => 'status-closed',
        };
    }

    /** @param array<string, mixed> $event */
    public static function fromEvent(array $event, ?\DateTimeImmutable $now = null): self
    {
        return EventSchedule::registrationStatus($event, $now);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
