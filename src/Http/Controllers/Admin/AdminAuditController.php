<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;

final class AdminAuditController extends Controller
{
    public function __construct(
        View $view,
        private readonly AuditLogRepository $audit,
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'action' => trim($request->string('action')),
            'target_type' => trim($request->string('target_type')),
            'page' => max(1, (int) $request->input('page', 1)),
        ];

        return $this->renderPage('admin/audit/index', [
            'title' => 'Audit',
            'logs' => $this->audit->paginateForAdmin($filters, 25),
            'filters' => $filters,
        ]);
    }
}
