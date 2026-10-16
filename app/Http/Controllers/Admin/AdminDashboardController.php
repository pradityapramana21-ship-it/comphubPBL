<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Dasbor administrator.
 *
 * Urutan isi halaman mengikuti urutan kerja administrator hari ini: apa yang
 * menunggu keputusan lebih dulu. Antrean tinjauan ada di paling atas,
 * bukan angka total di paling atas — angka tidak bisa diklik.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        EventRepository $events,
        ProviderProfileRepository $providers,
        ReportRepository $reports,
        UserRepository $users,
        ModerationLogRepository $moderationLogs,
    ): View {
        $stats = Cache::remember('admin.stats', now()->addMinutes(2), fn (): array => [
            'totalUser' => $users->countByRole()['user'] ?? 0,
            'totalProvider' => $providers->countAll(),
            'totalEvent' => $events->countAll(),
            'totalBookmark' => $events->paginatePublicCatalog(CatalogFilter::fromQuery([]))->total(),
            'openReports' => $reports->countOpen(),
        ]);

        return view('admin.dashboard', [
            'stats' => $stats,
            'reviewQueue' => $events->paginateReviewQueue(perPage: 8),
            'verificationQueue' => $providers->paginateVerificationQueue(perPage: 5),
            'reportQueue' => $reports->paginateOpenQueue(perPage: 5),
            'perStatus' => $events->countByStatus(),
            'perVerification' => $providers->countByVerificationStatus(),
            'ruleHits' => $moderationLogs->countByRuleKey(),
            'recentModeration' => $moderationLogs->paginateForAdmin(perPage: 8),
        ]);
    }
}
