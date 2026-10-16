<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\Category;
use App\Models\User;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Support\DateHelper;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Kaki semua controller.
 *
 * Isinya sengaja sedikit: hanya hal-hal yang dipakai SELURUH controller,
 * supaya kelas ini tetap menjadi titik경찰 yang mudah dibaca. Aturan bisnis
 * tidak pernah ditulis di sini — itu milik service.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use ValidatesRequests;

    /** Pesan sukses sekali tampil, dalam Bahasa Indonesia. */
    protected function flashSuccess(string $message): void
    {
        session()->flash('success', $message);
    }

    protected function flashError(string $message): void
    {
        session()->flash('error', $message);
    }

    /**
     * Pesan yang menjelaskan APA yang harus diperbaiki.
     *
     * UntukReject dan RequestChanges, ini bukan formalitas: tanpa alasan
     * yang bisa dipahami, penyedia tidak akan pernah memperbaikinya
     * (Panduan §4.5).
     */
    protected function flashFailure(string $message): void
    {
        session()->flash('error', $message);
    }

    /**
     * Data yang dibutuhkan hampir semua halaman.
     *
     * Dipanggil dari ViewServiceProvider untuk SEMUA view, supaya tidak
     * ada halaman yang lupa menampilkan navigasi atau penanda masuk.
     *
     * @return array<string, mixed>
     */
    protected function sharedViewData(): array
    {
        return [
            'appName' => config('app.name'),
            'dateHelper' => app(DateHelper::class),
        ];
    }

    /**
     * Penghitung untuk lencana di navbar. Di-cache supaya daftar kegiatan
     * tidak dihitung ulang tiap halaman.
     *
     * @return array<string, int>
     */
    protected function badgeCounts(User $user): array
    {
        return [
            'saved' => $user->bookmarks()->count(),
            'pending' => $user->isProvider() && $user->hasVerifiedProvider()
                ? app(EventRepository::class)
                    ->paginateForProvider(
                        $user->providerProfile->id,
                        \App\DataTransferObjects\CatalogFilter::fromQuery([])->withoutDeadlineFilter(),
                    )->total()
                : 0,
            'verification' => $user->isProvider() && $user->providerProfile?->verification_status !== VerificationStatus::Verified
                ? 1
                : 0,
            'reports' => $user->isAdmin() ? app(ReportRepository::class)->countOpen() : 0,
        ];
    }

    /**
     * Daftar kategori untuk menu filter.
     *
     * Diambil lewat CategoryCache, bukan Cache::remember biasa: cache
     * Laravel 13 tidak boleh mengembalikan objek PHP apa pun, jadi model
     * Eloquent yang disimpan di sana akan rusak saat dibaca.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function categories()
    {
        return app(\App\Support\CategoryCache::class)->all();
    }

    /**
     * Profil penyedia milik pengguna yang sedang login, atau null.
     */
    protected function profileFor(?User $user)
    {
        if ($user === null || ! $user->isProvider()) {
            return null;
        }

        return app(ProviderProfileRepository::class)->findByUserId($user->id);
    }
}