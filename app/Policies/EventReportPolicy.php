<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EventReport;
use App\Models\User;

/**
 * Otorisasi laporan.
 *
 * Identitas pelapor bersifat pribadi: hanya pelapor sendiri (untuk
 * mencabut laporannya) dan admin yang boleh melihat. Penyedia kegiatan
 * TIDAK boleh tahu siapa yang melaporkannya — itu bisa membuat orang
 * takut melapor.
 */
class EventReportPolicy
{
    public function view(User $user, EventReport $report): bool
    {
        return $user->isAdmin() || $report->reporter_user_id === $user->id;
    }

    /** Siapa yang boleh melapor diatur di controller, bukan di policy ini. */
    public function resolve(User $user, EventReport $report): bool
    {
        return $user->canAccessApplication() && $user->isAdmin();
    }

    /** Pelapor boleh menarik laporannya sendiri selama belum diproses. */
    public function withdraw(User $user, EventReport $report): bool
    {
        return $report->reporter_user_id === $user->id && $report->isPending();
    }
}
