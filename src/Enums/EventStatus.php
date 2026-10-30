<?php

declare(strict_types=1);

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Pending => 'Menunggu Tinjauan',
            self::ChangesRequested => 'Perlu Diperbaiki',
            self::Rejected => 'Ditolak',
            self::Approved => 'Tayang',
            self::Expired => 'Kedaluwarsa',
            self::Archived => 'Diarsipkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Expired, self::Archived => 'neutral',
            self::Pending, self::ChangesRequested => 'warning',
            self::Rejected => 'danger',
            self::Approved => 'success',
        };
    }

    public function isVisibleToPublic(): bool
    {
        return $this === self::Approved;
    }

    public function isEditableByProvider(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested, self::Rejected], true);
    }

    public function isInReviewQueue(): bool
    {
        return in_array($this, [self::Pending, self::ChangesRequested], true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Archived;
    }

    /** @return list<string> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending->value, self::Archived->value],
            self::Pending => [self::Approved->value, self::Rejected->value, self::ChangesRequested->value, self::Draft->value],
            self::ChangesRequested => [self::Pending->value, self::Archived->value],
            self::Rejected => [self::Pending->value, self::Archived->value],
            self::Approved => [self::Expired->value, self::Archived->value],
            self::Expired => [self::Archived->value],
            self::Archived => [],
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
