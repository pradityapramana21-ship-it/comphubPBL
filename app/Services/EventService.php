<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use App\Models\Event;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Services\Moderation\ModerationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seluruh aturan lifecycle kegiatan dalam satu tempat.
 *
 * Kelas ini adalah PENJAGA mesin status. Setiap kali status berubah, dia
 * memeriksa tiga hal: transisinya sah (EventStatus::canTransitionTo),
 * pelakunya berhak (dicek di Policy), dan pengubahannya masuk transaksi
 * supaya tidak pernah ada kegiatan approved tanpa moderation_log.
 *
 * Aturan yang TIDAK boleh dilanggar: hanya admin yang bisa menyetujui,
 * dan penyedia tidak pernah bisa menyetujui karyanya sendiri.
 */
class EventService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly ModerationLogRepository $moderationLogs,
        private readonly ModerationService $moderation,
        private readonly AuditLogService $audit,
        private readonly BookmarkService $bookmarks,
    ) {}

    // ------------------------------------------------------------ penyedia

    public function createDraft(ProviderProfile $provider, array $data): Event
    {
        return DB::transaction(function () use ($provider, $data): Event {
            $event = $this->events->create($data + [
                'provider_profile_id' => $provider->id,
                'status' => EventStatus::Draft,
                'risk_score' => 0,
            ]);

            return $event;
        });
    }

    /**
     * Penyedia mengajukan draf untuk ditinjau.
     *
     * Di sinilah mesin moderasi berjalan. Hasilnya SELALU Pending atau
     * Rejected — tidak pernah Approved (Panduan §7.4).
     */
    public function submit(ProviderProfile $provider, Event $event): Event
    {
        if ($event->provider_profile_id !== $provider->id) {
            throw ValidationException::withMessages([
                'event' => 'Kegiatan ini bukan milik organisasi Anda.',
            ]);
        }

        if (! $event->status->canTransitionTo(EventStatus::Pending)) {
            throw ValidationException::withMessages([
                'event' => "Kegiatan berstatus \"{$event->status->label()}\" tidak bisa diajukan ulang.",
            ]);
        }

        return DB::transaction(function () use ($event): Event {
            $context = $this->contextFor($event);
            $verdict = $this->moderation->inspect($context);

            $status = $verdict->resultingStatus();

            $event = $this->events->update($event->id, [
                'status' => $status,
                'risk_score' => $verdict->totalScore,
                'submitted_at' => now(),
                'published_at' => null,
                'rejection_reason' => $status === EventStatus::Rejected ? $verdict->summary() : null,
            ]);

            $this->moderation->inspectAndLog($context, $event->id);

            return $event->refresh();
        });
    }

    /**
     * Penyedia memperbaiki kegiatan yang ditolak atau diminta diperbaiki.
     */
    public function resubmit(ProviderProfile $provider, Event $event): Event
    {
        return $this->submit($provider, $event);
    }

    public function update(ProviderProfile $provider, Event $event, array $data): Event
    {
        if ($event->provider_profile_id !== $provider->id) {
            throw ValidationException::withMessages([
                'event' => 'Kegiatan ini bukan milik organisasi Anda.',
            ]);
        }

        if (! $event->status->isEditableByProvider()) {
            throw ValidationException::withMessages([
                'event' => "Kegiatan berstatus \"{$event->status->label()}\" tidak bisa disunting. Tarik kembali dulu untuk memperbarui.",
            ]);
        }

        return $this->events->update($event->id, $data);
    }

    /**
     * Penyedia menarik kegiatan yang belum tayang.
     *
     * Kegiatan yang sudah tayang diarsipkan, bukan dihapus — jejaknya
     * harus tetap terbaca (Panduan §6.4).
     */
    public function withdraw(ProviderProfile $provider, Event $event): Event
    {
        if ($event->provider_profile_id !== $provider->id) {
            throw ValidationException::withMessages([
                'event' => 'Kegiatan ini bukan milik organisasi Anda.',
            ]);
        }

        if ($event->status === EventStatus::Archived) {
            throw ValidationException::withMessages([
                'event' => 'Kegiatan ini sudah diarsipkan.',
            ]);
        }

        return DB::transaction(function () use ($event): Event {
            $this->bookmarks->purgeForEvent($event);

            $this->moderationLogs->record(
                eventId: $event->id,
                action: ModerationAction::Archive,
                reason: 'Ditarik oleh penyedia',
            );

            return $this->events->update($event->id, [
                'status' => EventStatus::Archived,
                'archived_at' => now(),
                'rejection_reason' => null,
            ]);
        });
    }

    // --------------------------------------------------------------- admin

    public function approve(User $admin, Event $event, ?string $note = null): Event
    {
        return $this->moderate($admin, $event, EventStatus::Approved, $note, $note);
    }

    public function reject(User $admin, Event $event, string $reason): Event
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi agar penyedia tahu apa yang harus diperbaiki.',
            ]);
        }

        return $this->moderate($admin, $event, EventStatus::Rejected, $reason, $reason);
    }

    public function requestChanges(User $admin, Event $event, string $note): Event
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => 'Tuliskan apa yang perlu diperbaiki.',
            ]);
        }

        return $this->moderate($admin, $event, EventStatus::ChangesRequested, $note, $note);
    }

    public function archive(User $admin, Event $event, ?string $reason = null): Event
    {
        return DB::transaction(function () use ($admin, $event, $reason): Event {
            $this->bookmarks->purgeForEvent($event);

            $this->moderationLogs->record(
                eventId: $event->id,
                action: ModerationAction::Archive,
                reason: $reason,
                actorUserId: $admin->id,
            );

            $this->audit->eventArchived($admin, $event, $reason);

            return $this->events->update($event->id, [
                'status' => EventStatus::Archived,
                'archived_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });
    }

    /**
     * Satu pintu untuk approve/reject/request-changes.
     */
    private function moderate(
        User $admin,
        Event $event,
        EventStatus $target,
        ?string $note,
        ?string $rejectionReason,
    ): Event {
        return DB::transaction(function () use ($admin, $event, $target, $note, $rejectionReason): Event {
            if (! $event->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'event' => sprintf(
                        'Tidak bisa mengubah status dari "%s" menjadi "%s".',
                        $event->status->label(),
                        $target->label(),
                    ),
                ]);
            }

            // Pengaman kedua terhadap penyalahgunaan: meski Policy sudah
            // menahan, pemeriksaan di sini memastikan hanya admin yang lolos
            // walau dipanggil dari tempat lain (command, job, test).
            if (! $admin->isAdmin()) {
                throw ValidationException::withMessages([
                    'event' => 'Hanya administrator yang dapat mengambil keputusan moderasi.',
                ]);
            }

            $attributes = [
                'status' => $target,
                'rejection_reason' => $rejectionReason,
                'published_at' => $target === EventStatus::Approved ? now() : null,
                'archived_at' => $target === EventStatus::Archived ? now() : null,
            ];

            $this->moderationLogs->record(
                eventId: $event->id,
                action: match ($target) {
                    EventStatus::Approved => ModerationAction::Approve,
                    EventStatus::Rejected => ModerationAction::Reject,
                    EventStatus::ChangesRequested => ModerationAction::RequestChanges,
                    default => ModerationAction::Archive,
                },
                reason: $note,
                riskScore: $event->risk_score,
                actorUserId: $admin->id,
            );

            $updated = $this->events->update($event->id, $attributes);

            match ($target) {
                EventStatus::Approved => $this->audit->eventApproved($admin, $updated),
                EventStatus::Rejected => $this->audit->eventRejected($admin, $updated, (string) $note),
                EventStatus::ChangesRequested => $this->audit->eventChangesRequested($admin, $updated, (string) $note),
                default => null,
            };

            return $updated;
        });
    }

    // -------------------------------------------------------------- helper

    public function contextFor(Event $event): ModerationContext
    {
        return new ModerationContext(
            title: $event->title,
            description: $event->description,
            requirements: $event->requirements,
            registrationUrl: $event->registration_url,
            platformUrl: $event->platform_url,
            locationText: $event->location_text,
            eventStartAt: $event->event_start_at,
            eventEndAt: $event->event_end_at,
            registrationDeadline: $event->registration_deadline,
            provider: $event->providerProfile,
            eventId: $event->id,
        );
    }

    /**
     * Riwayat keputusan untuk satu kegiatan, dipakai di panel admin dan
     * dashboard penyedia.
     */
    public function historyFor(Event $event): Collection
    {
        return $this->moderationLogs->forEvent($event->id);
    }
}
