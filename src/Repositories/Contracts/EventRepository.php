<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Core\Paginator;
use App\DataTransferObjects\CatalogFilter;

interface EventRepository
{
    public function paginatePublicCatalog(CatalogFilter $filter): Paginator;

    /** @return list<array<string, mixed>> */
    public function findLatestOpen(int $limit): array;

    /** @return list<array<string, mixed>> */
    public function findDeadlineSoon(int $limit): array;

    /** @return list<array<string, mixed>> */
    public function findApprovedByProvider(int $providerProfileId, int $limit = 50): array;

    public function paginateForProvider(int $providerProfileId, CatalogFilter $filter): Paginator;

    public function paginateForAdmin(CatalogFilter $filter, ?int $providerProfileId = null): Paginator;

    public function paginateReviewQueue(int $perPage = 20): Paginator;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function create(array $attributes): array;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function update(int $id, array $attributes): array;

    public function delete(int $id): void;

    public function archive(int $id): void;

    /** @return list<array{value: string, label: string}> */
    public function availableMonths(): array;

    public function countSubmissionsSince(int $providerProfileId, \DateTimeInterface $since): int;

    /** @return list<array<string, mixed>> */
    public function findPossibleDuplicates(int $providerProfileId, string $title, \DateTimeInterface $startDate): array;

    public function incrementBookmarkCount(int $id): void;

    public function decrementBookmarkCount(int $id): void;

    public function countAll(): int;

    public function countOpen(): int;

    /** @return array<string, int> */
    public function countByStatus(): array;

    /** @return array<string, mixed>|null */
    public function findApprovedBySlug(string $slug): ?array;

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array;

    public function countNewSince(string $since): int;

    /** @return list<array<string, mixed>> */
    public function findNewSince(string $since, int $limit = 50): array;

    /** @return array<string, mixed>|null */
    public function findByIdForProvider(int $id, int $providerProfileId): ?array;

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    public function incrementViewCount(int $id): void;
}
