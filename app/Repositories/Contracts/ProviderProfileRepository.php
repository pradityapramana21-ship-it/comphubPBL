<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ProviderProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProviderProfileRepository
{
    public function create(array $attributes): ProviderProfile;

    public function findById(int $id): ?ProviderProfile;

    public function findByUserId(int $userId): ?ProviderProfile;

    /** Antrean verifikasi admin, paling lama menunggu di atas. */
    public function paginateVerificationQueue(int $perPage = 20): LengthAwarePaginator;

    /** Hanya untuk mesin moderasi: profil yang boleh mengajukan kegiatan. */
    public function findVerifiedById(int $id): ?ProviderProfile;

    public function paginatePublicDirectory(?string $keyword = null, int $perPage = 12): LengthAwarePaginator;

    /** Halaman profil publik. Hanya profil terverifikasi yang boleh tampil. */
    public function findVerifiedBySlug(string $slug): ?ProviderProfile;

    public function update(int $id, array $attributes): ProviderProfile;

    public function countAll(): int;

    public function countByVerificationStatus(): array;
}
