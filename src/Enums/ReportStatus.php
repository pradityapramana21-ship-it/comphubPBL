<?php

declare(strict_types=1);

namespace App\Enums;

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

    public function isPending(): bool
    {
        return in_array($this, [self::Open, self::Reviewing], true);
    }

    /** @return list<string> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Reviewing->value, self::Resolved->value, self::Dismissed->value],
            self::Reviewing => [self::Resolved->value, self::Dismissed->value],
            self::Resolved, self::Dismissed => [self::Reviewing->value],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, $this->allowedTransitions(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
