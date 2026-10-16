<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\AuditLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya jalan untuk menulis jejak audit.
 *
 * Orang tidak boleh memanggil repository secara langsung, karena alasan
 * dan nama aksi akan menyimpang antar pemanggil — dan jejak yang
 * menyimpang tidak berguna saat menyelidiki insiden.
 *
 * Kunci-kunci aksi memakai titik sebagai pemisah hierarki, supaya bisa
 * difilter per prefix: "event.*", "provider.*", "user.*", "report.*".
 */
class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogs,
    ) {}

    /**
     * Catat aksi yang dilakukan pengguna yang sedang login.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?object $target = null,
        array $metadata = [],
        ?Request $request = null,
        ?User $actor = null,
    ): void {
        $actor ??= Auth::user();
        $request ??= request();

        $this->auditLogs->record(
            action: $action,
            actorUserId: $actor?->id,
            actorRole: $actor?->role?->value,
            targetType: $target !== null ? $target::class : null,
            targetId: $target?->getKey(),
            metadata: $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function eventApproved(User $admin, object $event, array $metadata = []): void
    {
        $this->record('event.approved', $event, $metadata, actor: $admin);
    }

    /** @param array<string, mixed> $metadata */
    public function eventRejected(User $admin, object $event, string $reason, array $metadata = []): void
    {
        $this->record('event.rejected', $event, $metadata + ['alasan' => $reason], actor: $admin);
    }

    /** @param array<string, mixed> $metadata */
    public function eventChangesRequested(User $admin, object $event, string $note, array $metadata = []): void
    {
        $this->record('event.changes_requested', $event, $metadata + ['catatan' => $note], actor: $admin);
    }

    public function eventArchived(User $admin, object $event, ?string $reason = null): void
    {
        $this->record('event.archived', $event, array_filter(['alasan' => $reason]), actor: $admin);
    }

    public function providerVerified(User $admin, object $provider): void
    {
        $this->record('provider.verified', $provider, actor: $admin);
    }

    public function providerRejected(User $admin, object $provider, string $reason): void
    {
        $this->record('provider.rejected', $provider, ['alasan' => $reason], actor: $admin);
    }

    public function userSuspended(User $admin, User $target, ?string $note = null): void
    {
        $this->record('user.suspended', $target, array_filter(['catatan' => $note]), actor: $admin);
    }

    public function userReactivated(User $admin, User $target): void
    {
        $this->record('user.reactivated', $target, actor: $admin);
    }

    public function userRoleChanged(User $admin, User $target, string $from, string $to): void
    {
        $this->record('user.role_changed', $target, ['dari' => $from, 'ke' => $to], actor: $admin);
    }

    public function reportResolved(User $admin, object $report, string $status): void
    {
        $this->record('report.resolved', $report, ['status' => $status], actor: $admin);
    }
}
