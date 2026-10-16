<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\EventRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan dan membatalkan penyimpanan kegiatan.
 *
 * ID pengguna SELALU diambil dari sesi, tidak pernah dari parameter
 * (Panduan §6.5). Forms yang mengirim user_id akan diabaikan.
 */
class BookmarkService
{
    public function __construct(
        private readonly BookmarkRepository $bookmarks,
        private readonly EventRepository $events,
    ) {}

    /**
     * @return bool true kalau baru disimpan, false kalau sudah ada
     */
    public function add(User $user, Event $event): bool
    {
        if ($event->status !== EventStatus::Approved) {
            return false;
        }

        $created = $this->bookmarks->add($user->id, $event->id);

        if ($created) {
            $this->events->incrementBookmarkCount($event->id);
        }

        return $created;
    }

    /**
     * @return bool true kalau benar-benar dihapus
     */
    public function remove(User $user, Event $event): bool
    {
        $removed = $this->bookmarks->remove($user->id, $event->id);

        if ($removed) {
            $this->events->decrementBookmarkCount($event->id);
        }

        return $removed;
    }

    /**
     * Simpan kalau belum ada, hapus kalau sudah ada.
     *
     * @return bool true kalau keadaan akhirnya "tersimpan"
     */
    public function toggle(User $user, Event $event): bool
    {
        if ($this->bookmarks->exists($user->id, $event->id)) {
            $this->remove($user, $event);

            return false;
        }

        return $this->add($user, $event);
    }

    public function isSaved(User $user, Event $event): bool
    {
        return $this->bookmarks->exists($user->id, $event->id);
    }

    public function paginateForUser(User $user, int $perPage = 12): LengthAwarePaginator
    {
        return $this->bookmarks->paginateForUser($user->id, $perPage);
    }

    /**
     * Status simpan untuk banyak kegiatan sekaligus.
     *
     * Dipakai halaman detail dan daftar katalog supaya tidak ada satu
     * query per kartu (Panduan NFR Kinerja).
     *
     * @param  list<int>  $eventIds
     * @return array<int, bool>
     */
    public function savedMap(User $user, array $eventIds): array
    {
        // Id yang bukan integer dibuang lebih dulu. Kumpulan kegiatan
        // bisa saja berisi baris tanpa id kalau sorgenya salah pilih
        // kolom, dan itu tidak tidak boleh menjatuhkan seluruh halaman
        // karena satu nilai(null).
        $ids = array_values(array_filter(
            $eventIds,
            static fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)),
        ));

        $ids = array_map(static fn (int|string $id): int => (int) $id, $ids);

        $saved = $this->bookmarks->eventIdsFor($user->id, $ids);

        return array_map(
            static fn (int $id): bool => in_array($id, $saved, true),
            $ids,
        );
    }

    /**
     * Hapus semua penyimpanan sebuah kegiatan ketika diarsipkan.
     */
    public function purgeForEvent(Event $event): int
    {
        return DB::transaction(function () use ($event): int {
            $removed = $this->bookmarks->removeForEvent($event->id);

            DB::table('events')->where('id', $event->id)->update(['bookmark_count' => 0]);

            return $removed;
        });
    }
}
