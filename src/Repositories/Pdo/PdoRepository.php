<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Database;
use App\Core\Paginator;

abstract class PdoRepository
{
    public function __construct(protected readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string|int> $query
     */
    protected function paginate(
        string $countSql,
        string $dataSql,
        array $params,
        int $page,
        int $perPage,
        string $path,
        array $query = [],
    ): Paginator {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $total = (int) ($this->database->selectOne($countSql, $params)['total'] ?? 0);
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $rows = $this->database->select(
            $dataSql . ' LIMIT :limit OFFSET :offset',
            $params + ['limit' => $perPage, 'offset' => $offset],
        );

        return new Paginator($rows, $total, $perPage, $page, $path, $query);
    }

    /** @param array<string, mixed> $attributes */
    protected function updateById(string $table, int $id, array $attributes, array $allowed): ?array
    {
        $this->assertIdentifier($table);
        $sets = [];

        foreach ($attributes as $column => $value) {
            if (in_array($column, $allowed, true)) {
                $this->assertIdentifier($column);
                $sets[] = $column . ' = :' . $column;
            }
        }

        if ($sets === []) {
            return $this->findRow($table, $id);
        }

        $sets[] = 'updated_at = now()';
        $params = ['id' => $id];

        foreach ($attributes as $column => $value) {
            if (in_array($column, $allowed, true)) {
                $params[$column] = $value;
            }
        }

        $this->database->execute(
            'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE id = :id',
            $params,
        );

        return $this->findRow($table, $id);
    }

    /** @return array<string, mixed>|null */
    protected function findRow(string $table, int $id): ?array
    {
        $this->assertIdentifier($table);

        return $this->database->selectOne('SELECT * FROM ' . $table . ' WHERE id = :id', ['id' => $id]);
    }

    protected function now(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
    }

    protected function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-z_]+$/', $identifier) !== 1) {
            throw new \InvalidArgumentException('Identifier SQL tidak valid.');
        }
    }
}
