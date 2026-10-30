<?php

declare(strict_types=1);

namespace App\Core;

final class Database
{
    private int $transactionLevel = 0;

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public static function connectFromConfig(): self
    {
        $config = Config::get('app.database');
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database'],
        );

        $pdo = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return new self($pdo);
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    public function transaction(callable $callback): mixed
    {
        $savepoint = 'trans_' . $this->transactionLevel;

        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
        }

        $this->transactionLevel++;

        try {
            $result = $callback($this);

            if ($this->transactionLevel === 1) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($this->transactionLevel === 1) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
            } else {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            }

            throw $exception;
        } finally {
            $this->transactionLevel--;
        }
    }

    /** @param array<string, mixed> $params */
    public function select(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /** @param array<string, mixed> $params */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /** @param array<string, mixed> $params */
    public function insert(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $id = $statement->fetchColumn();

        return (int) $id;
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }
}
