<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\Repositories\Contracts\AuditLogRepository;

final class PdoAuditLogRepository extends PdoRepository implements AuditLogRepository
{
    public function record(
        ?int $actorUserId,
        ?string $actorRole,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $metadata = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $id = $this->database->insert(
            'INSERT INTO audit_logs
                (actor_user_id, actor_role, action, target_type, target_id, metadata, ip_address, user_agent)
             VALUES (:actor, :role, :action, :target_type, :target_id, :metadata, :ip, :agent)
             RETURNING id',
            [
                'actor' => $actorUserId,
                'role' => $actorRole,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $ipAddress,
                'agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 1000),
            ],
        );

        return $this->database->selectOne('SELECT * FROM audit_logs WHERE id = :id', ['id' => $id]) ?? [];
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 25): Paginator
    {
        $where = ['1 = 1'];
        $params = [];

        foreach (['action' => 'action', 'target_type' => 'target_type'] as $key => $column) {
            if (is_string($filters[$key] ?? null) && $filters[$key] !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = $filters[$key];
            }
        }

        if (isset($filters['actor_user_id']) && (int) $filters['actor_user_id'] > 0) {
            $where[] = 'actor_user_id = :actor';
            $params['actor'] = (int) $filters['actor_user_id'];
        }

        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total FROM audit_logs WHERE ' . $clause,
            'SELECT * FROM audit_logs WHERE ' . $clause . ' ORDER BY created_at DESC, id DESC',
            $params,
            (int) ($filters['page'] ?? 1),
            $perPage,
            '/admin/audit',
            array_filter($filters, static fn (mixed $v): bool => is_scalar($v) && (string) $v !== ''),
        );
    }

    public function forTarget(string $targetType, int $targetId, int $limit = 50): array
    {
        return $this->database->select(
            'SELECT * FROM audit_logs WHERE target_type = :target_type AND target_id = :target_id
             ORDER BY created_at DESC, id DESC LIMIT :limit',
            [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'limit' => max(1, min($limit, 200)),
            ],
        );
    }

    public function forActor(int $actorUserId, int $limit = 50): array
    {
        return $this->database->select(
            'SELECT * FROM audit_logs WHERE actor_user_id = :actor
             ORDER BY created_at DESC, id DESC LIMIT :limit',
            ['actor' => $actorUserId, 'limit' => max(1, min($limit, 200))],
        );
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne('SELECT count(*) AS total FROM audit_logs')['total'] ?? 0);
    }
}
