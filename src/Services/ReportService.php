<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Repositories\Contracts\ReportRepository;

final class ReportService
{
    public function __construct(
        private readonly Database $database,
        private readonly ReportRepository $reports,
    ) {
    }

    /** @return array{report: array<string, mixed>, created: bool} */
    public function create(int $eventId, int $reporterUserId, ReportReason $reason, ?string $description): array
    {
        return $this->database->transaction(function () use ($eventId, $reporterUserId, $reason, $description): array {
            $existing = $this->reports->findExisting($eventId, $reporterUserId);

            if ($existing !== null) {
                return ['report' => $existing, 'created' => false];
            }

            return [
                'report' => $this->reports->create($eventId, $reporterUserId, $reason, $description),
                'created' => true,
            ];
        });
    }

    /** @return array<string, mixed> */
    public function withdraw(int $reportId, int $actorUserId): array
    {
        return $this->database->transaction(
            fn (): array => $this->reports->updateStatus(
                $reportId,
                ReportStatus::Dismissed,
                $actorUserId,
                'Ditarik oleh pelapor.',
            ),
        );
    }
}
