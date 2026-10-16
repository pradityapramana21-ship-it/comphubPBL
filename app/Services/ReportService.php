<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\User;
use App\Repositories\Contracts\ReportRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pelaporan kegiatan bermasalah.
 *
 * Identitas pelapor tidak pernah dikirim ke penyedia. Penyedia hanya
 * melihat bahwa laporannya sedang diproses dan apa hasil keputusannya —
 * bukan siapa yang melaporkannya (Panduan §4.5).
 */
class ReportService
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Pelapor yang sama tidak bisa melapor kegiatan yang sama dua kali.
     * Laporan yang sudah ada dikembalikan, bukan dibuat baru.
     */
    public function report(
        User $reporter,
        Event $event,
        ReportReason $reason,
        ?string $description,
    ): EventReport {
        // Pemilik tidak boleh melaporkan karyanya sendiri: laporan
        // seperti itu hanya membebani antrean admin dan tidak pernah
        // ditindaklanjuti. EventPolicy::report() sudah menolak lebih dulu,
        // ini pengaman kedua untuk pemanggil lain.
        $ownsProfile = $reporter->providerProfile !== null
            && $event->provider_profile_id === $reporter->providerProfile->id;

        if ($ownsProfile) {
            throw ValidationException::withMessages([
                'event' => 'Anda tidak dapat melaporkan kegiatan milik organisasi Anda sendiri.',
            ]);
        }

        return DB::transaction(function () use ($reporter, $event, $reason, $description): EventReport {
            $existing = $this->reports->findExisting($event->id, $reporter->id);

            // Laporan ganda dari orang yang sama tidak pernah bisa masuk
            // database: ada UNIQUE (event_id, reporter_user_id). Mengembalikan
            // baris lama tanpa memberi tahu membuat orang mengira laporannya
            // terkirim padahal tidak ada yang berubah, jadi lebih baik
            // diberi tahu terus terang.
            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'event' => $existing->isPending()
                        ? 'Anda sudah melaporkan kegiatan ini. Laporan Anda sedang diproses.'
                        : 'Anda sudah pernah melaporkan kegiatan ini.',
                ]);
            }

            $report = $this->reports->create($event->id, $reporter->id, $reason, $description);

            $this->audit->record('report.created', $report, ['alasan' => $reason->value]);

            return $report;
        });
    }

    public function open(User $admin, EventReport $report): EventReport
    {
        return $this->transition($admin, $report, ReportStatus::Reviewing, null);
    }

    public function resolve(User $admin, EventReport $report, string $note): EventReport
    {
        return $this->transition($admin, $report, ReportStatus::Resolved, $note);
    }

    public function dismiss(User $admin, EventReport $report, string $note): EventReport
    {
        return $this->transition($admin, $report, ReportStatus::Dismissed, $note);
    }

    /**
     * Pelapor boleh menarik laporannya sendiri selama belum diproses.
     * Setelah status Resolved atau Dismissed, catatannya tetap ada.
     */
    public function withdraw(User $reporter, EventReport $report): EventReport
    {
        if ($report->reporter_user_id !== $reporter->id) {
            throw ValidationException::withMessages([
                'report' => 'Anda hanya dapat menarik laporan milik Anda sendiri.',
            ]);
        }

        if (! $report->isPending()) {
            throw ValidationException::withMessages([
                'report' => 'Laporan yang sudah diproses tidak bisa ditarik.',
            ]);
        }

        $report->forceFill(['status' => ReportStatus::Dismissed])->save();

        return $report->refresh();
    }

    private function transition(
        User $admin,
        EventReport $report,
        ReportStatus $target,
        ?string $note,
    ): EventReport {
        if (! $report->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Laporan berstatus "%s" tidak bisa diubah menjadi "%s".',
                    $report->status->label(),
                    $target->label(),
                ),
            ]);
        }

        $updated = $this->reports->updateStatus($report->id, $target, $note, $admin->id);

        $this->audit->reportResolved($admin, $updated, $target->value);

        return $updated;
    }

    public function openCount(): int
    {
        return $this->reports->countOpen();
    }
}
