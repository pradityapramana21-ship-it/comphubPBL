<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use App\Models\Event;
use App\Repositories\Contracts\ModerationLogRepository;
use Illuminate\Support\Facades\DB;

/**
 * Menandai kegiatan yang sudah lewat tenggat.
 *
 * Ada dua lapisan, dan keduanya perlu:
 *
 *  1. Command terjadwal yang mengubah status menjadi Expired. Ini yang
 *     terlihat admin di dashboard.
 *
 *  2. Query daftar publik yang memfilter tenggat secara langsung. Lapisan
 *     kedua menutup celah bila command belum sempat berjalan — kegiatan
 *     yang tenggatnya lewat SEDANG itu juga harus hilang dari daftar aktif
 *     (Panduan FR-03, TC-DATE-01).
 */
class EventExpiryService
{
    public function __construct(
        private readonly ModerationLogRepository $moderationLogs,
    ) {}

    /**
     * Tandai semua kegiatan yang pendaftarannya sudah ditutup.
     *
     * @return int jumlah kegiatan yang ditandai
     */
    public function markExpired(): int
    {
        $affected = 0;

        // Dipotong per batch supaya tidak menahan kunci tabel terlalu lama
        // kalau someday basis datanya besar.
        Event::query()
            ->published()
            ->where('registration_deadline', '<', now())
            ->orderBy('id')
            ->chunkById(200, function ($events) use (&$affected): void {
                foreach ($events as $event) {
                    $event->forceFill(['status' => EventStatus::Expired])->save();

                    $this->moderationLogs->record(
                        eventId: $event->id,
                        action: ModerationAction::Expire,
                        reason: 'Tenggat pendaftaran sudah lewat.',
                        riskScore: $event->risk_score,
                    );

                    $affected++;
                }
            });

        return $affected;
    }

    /**
     * Apakah kegiatan ini masih bisa dibaca pengguna sebagai pendaftaran
     * yang aktif. Dipakai view supaya label tidak pernah berbohong walau
     * command belum sempat jalan.
     */
    public function isActiveFor(Event $event): bool
    {
        return $event->status === EventStatus::Approved
            && $event->registration_deadline->isFuture();
    }

    /**
     * Bersihkan penyimpanan kegiatan yang sudah tidak aktif.
     *
     * @return int jumlah bookmark yang dihapus
     */
    public function purgeBookmarksOfExpired(): int
    {
        $ids = Event::query()
            ->whereIn('status', [EventStatus::Expired->value, EventStatus::Archived->value])
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $removed = DB::table('bookmarks')->whereIn('event_id', $ids)->delete();

        DB::table('events')->whereIn('id', $ids)->update(['bookmark_count' => 0]);

        return $removed;
    }
}
