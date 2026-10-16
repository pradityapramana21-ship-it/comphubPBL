<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProviderProfile;
use App\Repositories\Contracts\ProviderProfileRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentProviderProfileRepository implements ProviderProfileRepository
{
    public function create(array $attributes): ProviderProfile
    {
        return ProviderProfile::create($attributes);
    }

    public function findById(int $id): ?ProviderProfile
    {
        return ProviderProfile::find($id);
    }

    public function findByUserId(int $userId): ?ProviderProfile
    {
        return ProviderProfile::query()->where('user_id', $userId)->first();
    }

    public function paginateVerificationQueue(int $perPage = 20): LengthAwarePaginator
    {
        return ProviderProfile::query()
            ->awaitingVerification()
            ->with('user')
            ->oldest('created_at')
            ->paginate(min($perPage, 100));
    }

    public function findVerifiedById(int $id): ?ProviderProfile
    {
        return ProviderProfile::query()->whereKey($id)->verified()->first();
    }

    public function paginatePublicDirectory(?string $keyword = null, int $perPage = 12): LengthAwarePaginator
    {
        return ProviderProfile::query()
            ->verified()
            ->search($keyword)
            ->withCount(['events' => fn (Builder $q) => $q->published()])
            // whereHas, BUKAN having('events_count', '>', 0). Kolom hasil
            // withCount() adalah alias SELECT, dan PostgreSQL menolaknya
            // di HAVING tanpa GROUP BY. whereHas menuliskan subquery EXISTS
            // yang benar sebagai filter WHERE.
            ->whereHas('events', fn (Builder $q) => $q->published())
            ->orderBy('organization_name')
            ->paginate(min($perPage, 48));
    }

    public function findVerifiedBySlug(string $slug): ?ProviderProfile
    {
        $needle = mb_strtolower(trim($slug));

        // Nama organisasi tidak punya kolom slug; dicocokkan dari bentuk
        // ter-normalisasi agar URL "/providers/bem-kampus-a" tetap berhasil.
        return ProviderProfile::query()
            ->verified()
            ->whereRaw(
                "lower(regexp_replace(organization_name, '[^a-zA-Z0-9]+', '-', 'g')) = ?",
                [$needle],
            )
            ->first();
    }

    public function update(int $id, array $attributes): ProviderProfile
    {
        $profile = ProviderProfile::findOrFail($id);
        $profile->fill($attributes)->save();

        return $profile->refresh();
    }

    public function countAll(): int
    {
        return ProviderProfile::query()->count();
    }

    public function countByVerificationStatus(): array
    {
        return ProviderProfile::query()
            ->selectRaw('verification_status, count(*) as total')
            ->groupBy('verification_status')
            ->pluck('total', 'verification_status')
            ->all();
    }
}
