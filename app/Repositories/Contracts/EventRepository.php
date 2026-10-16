<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DataTransferObjects\CatalogFilter;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventRepository
{
    /**
     * Satu-satunya query untuk daftar publik.
     *
     * Selalu mengembalikan kegiatan berstatus approved. Tidak ada jalur lain
     * yang boleh mengembalikan draft ke pengunjung tanpa login, karena
     * menebak ID adalah cara termurah membocorkan kegiatan yang belum
     * disetujui (Panduan FR-03).
     */
    public function paginatePublicCatalog(CatalogFilter $filter): LengthAwarePaginator;

    public function paginateForProvider(int $providerProfileId, CatalogFilter $filter): LengthAwarePaginator;

    public function paginateForAdmin(CatalogFilter $filter, ?int $providerProfileId = null): LengthAwarePaginator;

    /** Antrean review: pending + changes_requested, paling berisiko di atas. */
    public function paginateReviewQueue(int $perPage = 20): LengthAwarePaginator;

    /** Detail publik. Mengembalikan null bila statusnya belum approved. */
    public function findPublishedBySlug(string $slug): ?Event;

    /**
     * Pencarian kegiatan tanpa batasan status. KHUSUS untuk dua keperluan:
     * pengecekan duplikasi di mesin moderasi, dan panel admin. Jangan
     * pernah mengembalikan hasilnya ke view publik.
     */
    public function findBySlugAnyStatus(string $slug): ?Event;

    public function findById(int $id): ?Event;

    /** Dipakai aktivitas untuk mengecek kepemilikan sebelum pembaruan. */
    public function findOwnedByProvider(int $id, int $providerProfileId): ?Event;

    public function create(array $attributes): Event;

    public function update(int $id, array $attributes): Event;

    public function incrementViewCount(int $id): void;

    /** Jumlah pengajuan dalam jendela waktu tertentu — dasar aturan rate limit. */
    public function countSubmissionsSince(int $providerProfileId, \DateTimeInterface $since): int;

    /**
     * Kandidat duplikat: judul mirip pada provider yang sama dan tanggal
     * yang berdekatan. Dipakai aturan DuplicateEventRule.
     *
     * @return \Illuminate\Support\Collection<int, Event>
     */
    public function findPossibleDuplicates(int $providerProfileId, string $title, \DateTimeInterface $startDate): iterable;

    /** Naikkan penghitung bookmark dalam satu operasi atomik. */
    public function incrementBookmarkCount(int $id): void;

    /**
     * Turunkan penghitung bookmark, tapi tidak pernah di bawah nol.
     *
     * Menghapus penyimpanan terakhir harus mengembalikan 0, bukan -1 —
     * angka negatif akan tampil di UI dan merusak urutan popularitas.
     */
    public function decrementBookmarkCount(int $id): void;

    public function countAll(): int;

    public function countByStatus(): array;
}
