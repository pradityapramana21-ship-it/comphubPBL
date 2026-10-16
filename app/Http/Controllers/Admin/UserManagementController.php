<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manajemen akun.
 *
 * Policy menutup jalan berbahaya: admin tidak boleh menyentuh akun
 * admin lain, dan tidak boleh menyentuh akunnya sendiri lewat halaman
 * ini (Panduan §2). Yang terlihat di daftar sengaja tidak memuat
 * lencana "akun admin" yang bisa diklik.
 */
class UserManagementController extends Controller
{
    public function index(Request $request, UserRepository $users): View
    {
        return view('admin.users.index', [
            'users' => $users->paginateForAdmin(
                array_filter([
                    'keyword' => $request->query('q'),
                    'role' => $request->query('role'),
                    'status' => $request->query('status'),
                ]),
            ),
            'keyword' => trim((string) $request->query('q', '')),
            'perRole' => $users->countByRole(),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UserRepository $users,
        AuditLogService $audit,
    ): RedirectResponse {
        $actor = $request->user();

        // Status
        if ($request->has('account_status')) {
            $status = AccountStatus::from((string) $request->input('account_status'));

            $users->updateStatus($user->id, $status, $request->input('suspension_note'));

            $status === AccountStatus::Suspended
                ? $audit->userSuspended($actor, $user, $request->input('suspension_note'))
                : $audit->userReactivated($actor, $user);
        }

        // Peran
        if ($request->has('role')) {
            $from = $user->role;
            $to = UserRole::from((string) $request->input('role'));

            if ($from !== $to) {
                $user->forceFill(['role' => $to])->save();
                $audit->userRoleChanged($actor, $user->fresh(), $from->value, $to->value);
            }
        }

        $this->flashSuccess('Perubahan akun tersimpan.');

        return back();
    }
}
