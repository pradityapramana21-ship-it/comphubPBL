<?php

declare(strict_types=1);

namespace App\Providers;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\VerificationStatus;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Support\CategoryCache;
use App\Support\DateHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Data yang dibagikan ke semua tampilan.
 *
 * Kenapa di sini, bukan di setiap controller: daftar kategori, penanda
 * lencana, dan format tanggal dibutuhkan hampir di setiap halaman. Kalau
 * tiap controller mengirimkannya sendiri, satu halaman yang lupa
 * mengirimkannya akan langsung terlihat tidak konsisten.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view): void {
            $user = auth()->user();

            $view->with([
                'appName' => config('app.name'),
                'dateHelper' => app(DateHelper::class),
                'navCategories' => $this->categories(),
                'navProviderProfile' => $user?->isProvider() === true
                    ? app(ProviderProfileRepository::class)->findByUserId($user->id)
                    : null,
                'navVerificationPending' => $this->verificationPending($user),
                'navBadges' => $this->badges($user),
                'currentPath' => request()->path(),
            ]);
        });

        // Halaman 404/403/error juga boleh punya layout minimal, tapi tidak
        // perlu daftar kategori — menampilkannya bisa memancing query yang
        // tidak perlu saat ada yang salah.
        View::composer(['errors::*'], function ($view): void {
            $view->with(['dateHelper' => app(DateHelper::class)]);
        });
    }

    /** @return Collection<int, object> */
    private function categories()
    {
        // Cache yang sama dengan yang dipakai controller: satu sumber
        // kebenaran, dan tidak ada kunci yang harus remembering dua tempat.
        return app(CategoryCache::class)->all();
    }

    private function verificationPending(?object $user): bool
    {
        if ($user === null || ! $user->isProvider()) {
            return false;
        }

        return $user->providerProfile?->verification_status !== VerificationStatus::Verified;
    }

    /**
     * Lencana di navbar. Sengaja memakai query COUNT langsung, bukan
     * memuat daftar utuh — navbar muncul di semua halaman, jadi query
     * berat di sini akan terasa.
     *
     * @return array<string, int>
     */
    private function badges(?object $user): array
    {
        $zero = ['saved' => 0, 'providerEvents' => 0, 'reports' => 0, 'reviewQueue' => 0];

        if ($user === null) {
            return $zero;
        }

        try {
            if ($user->isAdmin()) {
                return [
                    'saved' => 0,
                    'providerEvents' => 0,
                    'reports' => app(ReportRepository::class)->countOpen(),
                    'reviewQueue' => $this->queueSize(),
                ];
            }

            if ($user->isProvider()) {
                return [
                    'saved' => 0,
                    'providerEvents' => $user->providerProfile !== null
                        ? $this->providerEventCount($user->providerProfile->id)
                        : 0,
                    'reports' => 0,
                    'reviewQueue' => 0,
                ];
            }

            return ['saved' => $user->bookmarks()->count(), 'providerEvents' => 0, 'reports' => 0, 'reviewQueue' => 0];
        } catch (\Throwable $e) {
            Log::warning('Gagal menghitung lencana navigasi: ' . $e->getMessage());

            return $zero;
        }
    }

    private function providerEventCount(int $providerProfileId): int
    {
        return Cache::remember(
            "provider.events.{$providerProfileId}",
            now()->addMinutes(5),
            fn (): int => app(EventRepository::class)
                ->paginateForProvider(
                    $providerProfileId,
                    CatalogFilter::fromQuery([])->withoutDeadlineFilter(),
                )->total(),
        );
    }

    private function queueSize(): int
    {
        return Cache::remember('admin.reviewQueue.size', now()->addMinutes(2), function (): int {
            $queue = app(EventRepository::class)->paginateReviewQueue(perPage: 1);

            return $queue instanceof LengthAwarePaginator ? $queue->total() : 0;
        });
    }
}
