<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Relasi user <-> kegiatan.
 *
 * Tulis memakai ON CONFLICT DO NOTHING, bukan cek-lalu-simpan: dua klik
 * bersamaan dari satu orang akan menghasilkan tepat satu baris, karena
 * PRIMARY KEY komposit yang menolaknya (Panduan FR-05).
 */
interface BookmarkRepository
{
    /** @return bool true kalau bookmark benar-benar baru dibuat */
    public function add(int $userId, int $eventId): bool;

    /** @return bool true kalau bookmark memang ada dan dihapus */
    public function remove(int $userId, int $eventId): bool;

    public function exists(int $userId, int $eventId): bool;

    /** @return array<int, int> daftar event_id milik user tersebut */
    public function eventIdsFor(int $userId, array $eventIds): array;

    /** Halaman "Tersimpan". Hanya kegiatan yang masih approved yang dikembalikan. */
    public function paginateForUser(int $userId, int $perPage = 12): LengthAwarePaginator;

    public function countForEvent(int $eventId): int;

    /** Hapus semua bookmark sebuah kegiatan; dipakai saat kegiatan diarsipkan. */
    public function removeForEvent(int $eventId): int;

    public function countAll(): int;
}
