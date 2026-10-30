<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Repositories\Contracts\UserRepository;

final class PdoUserRepository extends PdoRepository implements UserRepository
{
    private const SELECT = 'SELECT u.*, p.id AS provider_profile_id, p.organization_name,
        p.verification_status
        FROM users u LEFT JOIN provider_profiles p ON p.user_id = u.id';

    public function create(string $name, string $email, string $passwordHash, UserRole $role): array
    {
        $id = $this->database->insert(
            'INSERT INTO users (name, email, password, role, account_status)
             VALUES (:name, :email, :password, :role, :status) RETURNING id',
            [
                'name' => trim($name),
                'email' => mb_strtolower(trim($email)),
                'password' => $passwordHash,
                'role' => $role->value,
                'status' => AccountStatus::Active->value,
            ],
        );

        return $this->findById($id) ?? [];
    }

    public function findById(int $id): ?array
    {
        return $this->database->selectOne(self::SELECT . ' WHERE u.id = :id', ['id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE u.email = :email',
            ['email' => mb_strtolower(trim($email))],
        );
    }

    public function findActiveByEmail(string $email): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE u.email = :email AND u.account_status = :status',
            ['email' => mb_strtolower(trim($email)), 'status' => AccountStatus::Active->value],
        );
    }

    public function findActiveById(int $id): ?array
    {
        return $this->database->selectOne(
            self::SELECT . ' WHERE u.id = :id AND u.account_status = :status',
            ['id' => $id, 'status' => AccountStatus::Active->value],
        );
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE email = :email';
        $params = ['email' => mb_strtolower(trim($email))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->database->selectOne($sql . ' LIMIT 1', $params) !== null;
    }

    public function updateProfile(int $id, array $attributes): array
    {
        if (isset($attributes['email'])) {
            $attributes['email'] = mb_strtolower(trim((string) $attributes['email']));
        }

        return $this->updateById('users', $id, $attributes, ['name', 'email'])
            ?? throw new \RuntimeException('Pengguna tidak ditemukan.');
    }

    public function updateStatus(int $id, AccountStatus $status, ?string $note = null): array
    {
        $this->database->execute(
            'UPDATE users SET account_status = :status, suspended_at = :suspended_at,
                suspension_note = :note, updated_at = now() WHERE id = :id',
            [
                'status' => $status->value,
                'suspended_at' => $status === AccountStatus::Suspended ? $this->now() : null,
                'note' => $status === AccountStatus::Suspended ? $note : null,
                'id' => $id,
            ],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Pengguna tidak ditemukan.');
    }

    public function updateRole(int $id, UserRole $role): array
    {
        $this->database->execute(
            'UPDATE users SET role = :role, updated_at = now() WHERE id = :id',
            ['role' => $role->value, 'id' => $id],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Pengguna tidak ditemukan.');
    }

    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM users WHERE id = :id', ['id' => $id]);
    }

    public function updatePassword(int $id, string $passwordHash): array
    {
        $this->database->execute(
            'UPDATE users SET password = :password, updated_at = now() WHERE id = :id',
            ['password' => $passwordHash, 'id' => $id],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Pengguna tidak ditemukan.');
    }

    public function setRememberToken(int $id, ?string $tokenHash): void
    {
        $this->database->execute(
            'UPDATE users SET remember_token = :token, updated_at = now() WHERE id = :id',
            ['token' => $tokenHash, 'id' => $id],
        );
    }

    public function markEventsSeen(int $id): void
    {
        $this->database->execute(
            'UPDATE users SET events_seen_at = now(), updated_at = now() WHERE id = :id',
            ['id' => $id],
        );
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 20): Paginator
    {
        $where = ['1 = 1'];
        $params = [];
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        if ($keyword !== '') {
            $where[] = '(u.name ILIKE :keyword OR u.email ILIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }

        $role = (string) ($filters['role'] ?? '');
        if (in_array($role, UserRole::values(), true)) {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, AccountStatus::values(), true)) {
            $where[] = 'u.account_status = :status';
            $params['status'] = $status;
        }

        $sorts = ['terbaru' => 'u.created_at DESC', 'nama' => 'u.name ASC', 'peran' => 'u.role ASC'];
        $order = $sorts[(string) ($filters['sort'] ?? '')] ?? 'u.created_at DESC';
        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total FROM users u WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY ' . $order,
            $params,
            (int) ($filters['page'] ?? 1),
            $perPage,
            '/admin/pengguna',
            array_filter($filters, static fn (mixed $v): bool => is_scalar($v) && (string) $v !== ''),
        );
    }

    public function countByRole(): array
    {
        $rows = $this->database->select('SELECT role, count(*) AS total FROM users GROUP BY role');
        $counts = array_fill_keys(UserRole::values(), 0);

        foreach ($rows as $row) {
            $counts[(string) $row['role']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne('SELECT count(*) AS total FROM users')['total'] ?? 0);
    }
}
