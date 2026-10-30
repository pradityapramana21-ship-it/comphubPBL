<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RegistrationStatus;

final class EventSchedule
{
    /** @param array<string, mixed> $event */
    public static function registrationStatus(array $event, ?\DateTimeImmutable $now = null): RegistrationStatus
    {
        $now ??= new \DateTimeImmutable('now');
        $deadline = self::moment($event['registration_deadline'] ?? null);
        $start = self::moment($event['registration_start_at'] ?? null);

        if ($deadline !== null && $deadline < $now) {
            return RegistrationStatus::Closed;
        }

        if ($start !== null && $start > $now) {
            return RegistrationStatus::Upcoming;
        }

        return RegistrationStatus::Open;
    }

    /** @param array<string, mixed> $event */
    public static function isArchived(array $event): bool
    {
        return ($event['archived_at'] ?? null) !== null && ($event['archived_at'] ?? '') !== '';
    }

    private static function moment(mixed $value): ?\DateTimeImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
