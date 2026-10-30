<?php

declare(strict_types=1);

namespace App\Enums;

enum EventType: string
{
    case Competition = 'competition';
    case Webinar = 'webinar';
    case Seminar = 'seminar';

    public function label(): string
    {
        return match ($this) {
            self::Competition => 'Lomba',
            self::Webinar => 'Webinar',
            self::Seminar => 'Seminar',
        };
    }

    public function path(): string
    {
        return match ($this) {
            self::Competition => '/lomba',
            self::Webinar => '/webinar',
            self::Seminar => '/seminar',
        };
    }

    public static function fromCategorySlug(string $slug): ?self
    {
        return match ($slug) {
            'lomba' => self::Competition,
            'webinar' => self::Webinar,
            'seminar' => self::Seminar,
            default => null,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
