<?php

declare(strict_types=1);

namespace App\Enums;

enum RuleSeverity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
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

    public function tone(): string
    {
        return match ($this) {
            self::Info, self::Low => 'neutral',
            self::Medium => 'warning',
            self::High, self::Critical => 'danger',
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

    public function rank(): int
    {
        $index = array_search($this, self::cases(), true);

        return $index === false ? 0 : $index;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
