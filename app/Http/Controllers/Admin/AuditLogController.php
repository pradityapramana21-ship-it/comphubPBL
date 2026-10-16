<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AuditLogRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jejak audit.
 *
 * Hanya baca. Tidak ada aksi approve, ubah, atau hapus di halaman ini —
 * bukan karena disembunyikan, tapi karena tujuannya memang untuk
 * membaca. Sanitiser di repository sudah membuang kata sandi, token,
 * dan alamat email sebelum data ditulis (Panduan §8).
 */
class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogRepository $auditLogs): View
    {
        $this->authorize('view-audit-log');

        return view('admin.audit-logs.index', [
            'logs' => $auditLogs->paginateForAdmin(
                array_filter([
                    'action' => $request->query('action'),
                    'actor_user_id' => $request->query('actor'),
                    'target_type' => $request->query('target'),
                ]),
                perPage: 25,
            ),
            'total' => $auditLogs->countAll(),
            'actions' => $this->knownActions(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function knownActions(): array
    {
        return [
            'user.registered', 'user.logged_in', 'user.logged_out',
            'user.password_changed', 'user.suspended', 'user.reactivated',
            'user.role_changed',
            'provider.registered', 'provider.verified', 'provider.rejected',
            'provider.resubmitted', 'provider.reopened',
            'event.approved', 'event.rejected', 'event.changes_requested',
            'event.archived',
            'report.created', 'report.resolved',
        ];
    }
}
