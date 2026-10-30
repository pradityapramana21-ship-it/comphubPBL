<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\Enums\ModerationAction;
use App\Repositories\Contracts\ModerationLogRepository;

final class PdoModerationLogRepository extends PdoRepository implements ModerationLogRepository
{
    private const SELECT = 'SELECT m.*, u.name AS actor_name, e.title AS event_title
        FROM moderation_logs m
        LEFT JOIN users u ON u.id = m.actor_user_id
        JOIN events e ON e.id = m.event_id';

    public function record(
        int $eventId,
        ?int $actorUserId,
        ModerationAction $action,
        ?string $ruleKey = null,
        ?string $reason = null,
        ?int $riskScore = null,
        ?array $metadata = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
    ): array {
        $id = $this->database->insert(
            'INSERT INTO moderation_logs
                (event_id, actor_user_id, action, rule_key, reason, risk_score, metadata, previous_status, new_status)
             VALUES (:event_id, :actor, :action, :rule_key, :reason, :risk_score, :metadata, :previous_status, :new_status)
             RETURNING id',
            [
                'event_id' => $eventId,
                'actor' => $actorUserId,
                'action' => $action->value,
                'rule_key' => $ruleKey,
                'reason' => $reason,
                'risk_score' => $riskScore,
                'metadata' => $metadata === null ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
            ],
        );

        return $this->database->selectOne(self::SELECT . ' WHERE m.id = :id', ['id' => $id]) ?? [];
    }

    public function forEvent(int $eventId, int $limit = 50): array
    {
        return $this->database->select(
            self::SELECT . ' WHERE m.event_id = :event_id ORDER BY m.created_at DESC, m.id DESC LIMIT :limit',
            ['event_id' => $eventId, 'limit' => max(1, min($limit, 200))],
        );
    }

    public function latestForEvent(int $eventId): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE m.event_id = :event_id ORDER BY m.created_at DESC, m.id DESC LIMIT 1',
            ['event_id' => $eventId],
        );
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 25): Paginator
    {
        $where = ['1 = 1'];
        $params = [];

        if (isset($filters['event_id']) && (int) $filters['event_id'] > 0) {
            $where[] = 'm.event_id = :event_id';
            $params['event_id'] = (int) $filters['event_id'];
        }

        if (in_array($filters['action'] ?? '', ModerationAction::values(), true)) {
            $where[] = 'm.action = :action';
            $params['action'] = $filters['action'];
        }

        if (is_string($filters['rule_key'] ?? null) && $filters['rule_key'] !== '') {
            $where[] = 'm.rule_key = :rule_key';
            $params['rule_key'] = $filters['rule_key'];
        }

        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total FROM moderation_logs m WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY m.created_at DESC, m.id DESC',
            $params,
            (int) ($filters['page'] ?? 1),
            $perPage,
            '/admin/audit',
            array_filter($filters, static fn (mixed $v): bool => is_scalar($v) && (string) $v !== ''),
        );
    }

    public function countByRuleKey(?string $ruleKey = null): array
    {
        $sql = 'SELECT rule_key, count(*) AS total FROM moderation_logs WHERE rule_key IS NOT NULL';
        $params = [];

        if ($ruleKey !== null) {
            $sql .= ' AND rule_key = :rule_key';
            $params['rule_key'] = $ruleKey;
        }

        $rows = $this->database->select($sql . ' GROUP BY rule_key ORDER BY total DESC', $params);
        $counts = [];

        foreach ($rows as $row) {
            $counts[(string) $row['rule_key']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne('SELECT count(*) AS total FROM moderation_logs')['total'] ?? 0);
    }
}
