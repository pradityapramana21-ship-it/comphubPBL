<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;

final class ModerationService
{
    public function __construct(
        private readonly Database $database,
        private readonly EventRepository $events,
        private readonly ModerationLogRepository $logs,
        private readonly AuditLogRepository $audit,
        private readonly RevisionTokenService $tokens,
    ) {
    }

    /** @return array<string, mixed> */
    public function approve(int $eventId, int $actorId, string $ip = '', ?string $userAgent = null): array
    {
        return $this->transition($eventId, $actorId, EventStatus::Approved, ModerationAction::Approve, null, $ip, $userAgent);
    }

    /** @return array<string, mixed> */
    public function reject(int $eventId, int $actorId, string $reason, string $ip = '', ?string $userAgent = null): array
    {
        $this->requireReason($reason);

        return $this->transition($eventId, $actorId, EventStatus::Rejected, ModerationAction::Reject, trim($reason), $ip, $userAgent);
    }

    /** @return array{event: array<string, mixed>, token: string} */
    public function requestChanges(int $eventId, int $actorId, string $reason, string $ip = '', ?string $userAgent = null): array
    {
        $this->requireReason($reason);

        return $this->database->transaction(function () use ($eventId, $actorId, $reason, $ip, $userAgent): array {
            $event = $this->events->findById($eventId);
            $current = $event === null ? null : EventStatus::tryFrom((string) ($event['status'] ?? ''));

            if ($event === null || $current === null || ! $current->canTransitionTo(EventStatus::ChangesRequested)) {
                throw new ValidationException(['status' => ['Revisi tidak dapat diminta dari status saat ini.']]);
            }

            $event = $this->events->update($eventId, [
                'status' => EventStatus::ChangesRequested->value,
                'published_at' => null,
                'archived_at' => null,
                'rejection_reason' => trim($reason),
            ]);
            $token = $this->tokens->issue($eventId);

            $this->logs->record(
                $eventId,
                $actorId,
                ModerationAction::RequestChanges,
                null,
                trim($reason),
                null,
                ['token_issued' => true],
                $current->value,
                EventStatus::ChangesRequested->value,
            );
            $this->audit($actorId, 'event.request_changes', $eventId, ['reason' => trim($reason)], $ip, $userAgent);

            return ['event' => $event, 'token' => $token];
        });
    }

    /** @return array{event: array<string, mixed>, token: string} */
    public function reissueToken(int $eventId, int $actorId, string $ip = '', ?string $userAgent = null): array
    {
        $event = $this->events->findById($eventId);

        if ($event === null || ($event['status'] ?? '') !== EventStatus::ChangesRequested->value) {
            throw new ValidationException(['status' => ['Tautan revisi hanya dapat diterbitkan untuk pengajuan yang diminta diperbaiki.']]);
        }

        return $this->database->transaction(function () use ($eventId, $actorId, $ip, $userAgent): array {
            $token = $this->tokens->issue($eventId);
            $this->logs->record(
                $eventId,
                $actorId,
                ModerationAction::RequestChanges,
                null,
                'Tautan revisi diterbitkan ulang.',
                null,
                ['token_reissued' => true],
                EventStatus::ChangesRequested->value,
                EventStatus::ChangesRequested->value,
            );
            $this->audit($actorId, 'event.token.reissue', $eventId, [], $ip, $userAgent);

            return ['event' => $this->events->findById($eventId) ?? [], 'token' => $token];
        });
    }

    /** @return array<string, mixed> */
    public function archive(int $eventId, int $actorId, string $ip = '', ?string $userAgent = null): array
    {
        return $this->database->transaction(function () use ($eventId, $actorId, $ip, $userAgent): array {
            $event = $this->events->findById($eventId);

            if ($event === null) {
                throw new ValidationException(['event' => ['Kegiatan tidak ditemukan.']]);
            }

            if (($event['status'] ?? '') === EventStatus::Archived->value) {
                return $event;
            }

            $this->tokens->revoke($eventId);
            $updated = $this->events->update($eventId, [
                'status' => EventStatus::Archived->value,
                'archived_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            ]);

            $this->logs->record(
                $eventId,
                $actorId,
                ModerationAction::Archive,
                null,
                null,
                null,
                null,
                (string) ($event['status'] ?? ''),
                EventStatus::Archived->value,
            );
            $this->audit($actorId, 'event.archive', $eventId, ['title' => $event['title']], $ip, $userAgent);

            return $updated;
        });
    }

    /** @return array<string, mixed> */
    private function transition(
        int $eventId,
        int $actorId,
        EventStatus $next,
        ModerationAction $action,
        ?string $reason,
        string $ip,
        ?string $userAgent,
    ): array {
        return $this->database->transaction(function () use ($eventId, $actorId, $next, $action, $reason, $ip, $userAgent): array {
            $event = $this->events->findById($eventId);

            if ($event === null) {
                throw new ValidationException(['event' => ['Kegiatan tidak ditemukan.']]);
            }

            $current = EventStatus::tryFrom((string) ($event['status'] ?? ''));

            if ($current === null || ! $current->canTransitionTo($next)) {
                throw new ValidationException(['status' => ['Perubahan status tidak diizinkan dari status saat ini.']]);
            }

            $attributes = [
                'status' => $next->value,
                'rejection_reason' => $reason,
                'archived_at' => null,
            ];

            if ($next === EventStatus::Approved) {
                $attributes['published_at'] = ($event['published_at'] ?? null) ?: (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
            } else {
                $attributes['published_at'] = null;
            }

            $updated = $this->events->update($eventId, $attributes);

            if ($next === EventStatus::Approved) {
                $this->tokens->revoke($eventId);
            }

            $this->logs->record(
                $eventId,
                $actorId,
                $action,
                null,
                $reason,
                null,
                null,
                $current->value,
                $next->value,
            );
            $this->audit($actorId, 'event.' . $action->value, $eventId, ['previous' => $current->value, 'new' => $next->value], $ip, $userAgent);

            return $updated;
        });
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new ValidationException(['reason' => ['Alasan wajib diisi.']]);
        }
    }

    /** @param array<string, mixed> $metadata */
    private function audit(int $actorId, string $action, int $eventId, array $metadata, string $ip, ?string $userAgent): void
    {
        $this->audit->record(
            $actorId,
            UserRole::Admin->value,
            $action,
            'event',
            $eventId,
            $metadata,
            $ip,
            $userAgent,
        );
    }
}
