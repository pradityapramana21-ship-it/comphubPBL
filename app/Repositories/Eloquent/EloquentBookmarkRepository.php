<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\BookmarkRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Penulisan memakai INSERT ... ON CONFLICT DO NOTHING.
 *
 * Kalau dipakai cek-lalu-simpan (SELECT lalu INSERT), dua klik bersamaan
 * bisa lolos bersamaan dan menghasilkan baris ganda. Di sini PRIMARY KEY
 * komposit yang menolak — jadi tidak ada kondisi balapan yang perlu
 * diuruskan di kode (Panduan FR-05, §9.2).
 */
class EloquentBookmarkRepository implements BookmarkRepository
{
    public function add(int $userId, int $eventId): bool
    {
        // PENTING: DB::insert() mengembalikan BOOL, bukan jumlah baris.
        // Memeriksa `=== 1` akan selalu salah dan membuat caller menganggap
        // penyimpanan gagal padahal berhasil. Yang kita butuhkan adalah
        // "apakah ada baris yang benar-benar masuk?" — jawabannya datang dari
        // klausa RETURNING yang hanya mengembalikan baris kalau INSERT
        // benar-benar terjadi (bukan saat konflik).
        $inserted = DB::select(
            'INSERT INTO bookmarks (user_id, event_id, created_at)
             VALUES (?, ?, ?)
             ON CONFLICT (user_id, event_id) DO NOTHING
             RETURNING user_id',
            [$userId, $eventId, now()],
        );

        return $inserted !== [];
    }

    public function remove(int $userId, int $eventId): bool
    {
        return DB::delete(
            'DELETE FROM bookmarks WHERE user_id = ? AND event_id = ?',
            [$userId, $eventId],
        ) === 1;
    }

    public function exists(int $userId, int $eventId): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM bookmarks WHERE user_id = ? AND event_id = ?',
            [$userId, $eventId],
        ) !== null;
    }

    public function eventIdsFor(int $userId, array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        return DB::table('bookmarks')
            ->where('user_id', $userId)
            ->whereIn('event_id', $eventIds)
            ->pluck('event_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function paginateForUser(int $userId, int $perPage = 12): LengthAwarePaginator
    {
        $user = User::findOrFail($userId);

        return $user->bookmarkedEvents()
            // Kegiatan yang sudah diarsipkan atau ditolak hilang dari daftar
            // "Tersimpan" — menyimpan tidak memaksa kegiatan itu tetap tayang.
            ->published()
            ->with(['category', 'providerProfile'])
            ->paginate(min($perPage, 48));
    }

    public function countForEvent(int $eventId): int
    {
        return DB::table('bookmarks')->where('event_id', $eventId)->count();
    }

    public function removeForEvent(int $eventId): int
    {
        return DB::table('bookmarks')->where('event_id', $eventId)->delete();
    }

    public function countAll(): int
    {
        return DB::table('bookmarks')->count();
    }
}
