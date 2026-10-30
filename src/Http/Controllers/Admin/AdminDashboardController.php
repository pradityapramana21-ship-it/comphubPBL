<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Repositories\Contracts\UserRepository;

final class AdminDashboardController extends Controller
{
    public function __construct(
        View $view,
        private readonly UserRepository $users,
        private readonly EventRepository $events,
        private readonly ProviderProfileRepository $providers,
        private readonly ReportRepository $reports,
        private readonly AuditLogRepository $audit,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->renderPage('admin/index', [
            'title' => 'Admin',
            'userCounts' => $this->users->countByRole(),
            'userTotal' => $this->users->countAll(),
            'eventTotal' => $this->events->countAll(),
            'eventStatuses' => $this->events->countByStatus(),
            'providerStatuses' => $this->providers->countByVerificationStatus(),
            'providerTotal' => $this->providers->countAll(),
            'openReports' => $this->reports->countOpen(),
            'auditTotal' => $this->audit->countAll(),
            'recentAudit' => $this->audit->paginateForAdmin([], 8)->items,
        ]);
    }
}
