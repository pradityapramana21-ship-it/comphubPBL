<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\ReportRepository;

final class AdminReportController extends Controller
{
    public function __construct(
        View $view,
        private readonly ReportRepository $reports,
        private readonly AuditLogRepository $audit,
        private readonly Auth $auth,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $status = $request->string('status');
        $reason = $request->string('reason');
        $filters = [
            'status' => in_array($status, ReportStatus::values(), true) ? $status : '',
            'reason' => in_array($reason, ReportReason::values(), true) ? $reason : '',
            'page' => max(1, (int) $request->input('page', 1)),
        ];

        return $this->renderPage('admin/reports/index', [
            'title' => 'Laporan',
            'reports' => $this->reports->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => ReportStatus::values(),
            'reasons' => ReportReason::values(),
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) (($request->attribute('route', []))['report'] ?? 0);
        $report = $this->reports->findById($id);

        if ($report === null) {
            $this->abort(404);
        }

        $data = (new Validator($request->all(), [], [
            'status' => 'required|in:' . implode(',', ReportStatus::values()),
            'resolution_note' => 'nullable|string|max:2000',
        ], [], [
            'status' => 'status laporan',
            'resolution_note' => 'catatan penyelesaian',
        ]))->validate();

        $status = ReportStatus::from((string) $data['status']);
        $this->reports->updateStatus(
            (int) $report['id'],
            $status,
            (int) $this->auth->id(),
            $data['resolution_note'] ?? null,
        );
        $this->audit->record(
            $this->auth->id(),
            UserRole::Admin->value,
            'report.update',
            'report',
            (int) $report['id'],
            ['status' => $status->value],
            $request->ip(),
            $request->header('user-agent'),
        );

        app()->session()->flash('status', 'Laporan diperbarui.');

        return $this->redirect(url('/admin/laporan'));
    }
}
