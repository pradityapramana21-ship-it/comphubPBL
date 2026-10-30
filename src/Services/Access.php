<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;

final class Access
{
    /** @param array<string, mixed>|null $user @param array<string, mixed> $event */
    public function canViewEvent(?array $user, array $event): bool
    {
        if ($this->isAdmin($user) || ($event['status'] ?? '') === EventStatus::Approved->value) {
            return true;
        }

        return $this->isActive($user) && $this->ownsEvent($user, $event);
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $event */
    public function canUpdateEvent(?array $user, array $event): bool
    {
        if (! $this->isActive($user)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isProvider($user)
            && $this->ownsEvent($user, $event)
            && in_array($event['status'] ?? '', [
                EventStatus::Draft->value,
                EventStatus::ChangesRequested->value,
                EventStatus::Rejected->value,
            ], true);
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $event */
    public function canDeleteEvent(?array $user, array $event): bool
    {
        if (! $this->isActive($user)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isProvider($user)
            && $this->ownsEvent($user, $event)
            && ($event['status'] ?? '') === EventStatus::Draft->value;
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $event */
    public function canSubmitEvent(?array $user, array $event): bool
    {
        return $this->isActive($user)
            && $this->isProvider($user)
            && ($user['verification_status'] ?? null) === VerificationStatus::Verified->value
            && $this->ownsEvent($user, $event)
            && in_array($event['status'] ?? '', [
                EventStatus::Draft->value,
                EventStatus::ChangesRequested->value,
                EventStatus::Rejected->value,
            ], true);
    }

    /** @param array<string, mixed>|null $user */
    public function canModerateEvent(?array $user): bool
    {
        return $this->isAdmin($user);
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $event */
    public function canBookmark(?array $user, array $event): bool
    {
        return $this->isActive($user)
            && ($event['status'] ?? '') === EventStatus::Approved->value
            && ($event['archived_at'] ?? null) === null
            && RegistrationStatus::fromEvent($event) === RegistrationStatus::Open;
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $event */
    public function canReport(?array $user, array $event): bool
    {
        return $this->isActive($user)
            && ($user['role'] ?? '') === UserRole::User->value
            && ($event['status'] ?? '') === EventStatus::Approved->value
            && ! $this->ownsEvent($user, $event);
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $report */
    public function canViewReport(?array $user, array $report): bool
    {
        return $this->isAdmin($user)
            || ($this->isActive($user) && (int) ($report['reporter_user_id'] ?? 0) === (int) ($user['id'] ?? 0));
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $report */
    public function canWithdrawReport(?array $user, array $report): bool
    {
        return $this->isActive($user)
            && (int) ($report['reporter_user_id'] ?? 0) === (int) ($user['id'] ?? 0)
            && in_array($report['status'] ?? '', [ReportStatus::Open->value, ReportStatus::Reviewing->value], true);
    }

    /** @param array<string, mixed>|null $user @param array<string, mixed> $profile */
    public function canUpdateProviderProfile(?array $user, array $profile): bool
    {
        return $this->isActive($user)
            && ($this->isAdmin($user) || (int) ($profile['user_id'] ?? 0) === (int) ($user['id'] ?? 0));
    }

    /** @param array<string, mixed>|null $actor @param array<string, mixed> $target */
    public function canManageUser(?array $actor, array $target): bool
    {
        return $this->isAdmin($actor)
            && (int) ($actor['id'] ?? 0) !== (int) ($target['id'] ?? 0)
            && ($target['role'] ?? '') !== UserRole::Admin->value;
    }

    /** @param array<string, mixed>|null $actor @param array<string, mixed> $target */
    public function canChangeRole(?array $actor, array $target): bool
    {
        return $this->canManageUser($actor, $target);
    }

    /** @param array<string, mixed>|null $user */
    private function isAdmin(?array $user): bool
    {
        return $this->isActive($user) && ($user['role'] ?? '') === UserRole::Admin->value;
    }

    /** @param array<string, mixed>|null $user */
    private function isProvider(?array $user): bool
    {
        return ($user['role'] ?? '') === UserRole::Provider->value;
    }

    /** @param array<string, mixed>|null $user */
    private function isActive(?array $user): bool
    {
        return $user !== null && ($user['account_status'] ?? '') === 'active';
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $event */
    private function ownsEvent(array $user, array $event): bool
    {
        $profileId = (int) ($user['provider_profile_id'] ?? 0);

        return $profileId > 0 && $profileId === (int) ($event['provider_profile_id'] ?? -1);
    }
}
