<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Core\Paginator;
use App\Enums\VerificationStatus;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Support\SlugGenerator;

final class PdoProviderProfileRepository extends PdoRepository implements ProviderProfileRepository
{
    private const SELECT = 'SELECT p.*, u.email AS user_email, u.name AS user_name,
        u.account_status AS user_status
        FROM provider_profiles p JOIN users u ON u.id = p.user_id';

    public function create(array $attributes): array
    {
        $id = $this->database->insert(
            'INSERT INTO provider_profiles
                (user_id, organization_name, campus_name, official_email, website_url, description,
                 verification_document_path)
             VALUES (:user_id, :organization_name, :campus_name, :official_email, :website_url, :description,
                 :verification_document_path)
             RETURNING id',
            [
                'user_id' => $attributes['user_id'],
                'organization_name' => $attributes['organization_name'],
                'campus_name' => $attributes['campus_name'],
                'official_email' => $attributes['official_email'],
                'website_url' => $attributes['website_url'] ?? null,
                'description' => $attributes['description'] ?? null,
                'verification_document_path' => $attributes['verification_document_path'] ?? null,
            ],
        );

        return $this->findById($id) ?? [];
    }

    public function findById(int $id): ?array
    {
        return $this->database->selectOne(self::SELECT . ' WHERE p.id = :id', ['id' => $id]);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->database->selectOne(self::SELECT . ' WHERE p.user_id = :user_id', ['user_id' => $userId]);
    }

    public function paginateVerificationQueue(int $perPage = 20): Paginator
    {
        $where = "p.verification_status = 'pending' AND u.email NOT ILIKE '%.invalid'";

        return $this->paginate(
            'SELECT count(*) AS total FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE ' . $where,
            self::SELECT . ' WHERE ' . $where . ' ORDER BY p.created_at ASC',
            [],
            1,
            $perPage,
            '/admin/penyedia',
        );
    }

    public function paginateForAdmin(?string $keyword = null, string $status = '', int $perPage = 20, int $page = 1): Paginator
    {
        $where = ['1 = 1'];
        $params = [];
        $keyword = trim((string) $keyword);

        if ($keyword !== '') {
            $where[] = '(p.organization_name ILIKE :keyword OR p.campus_name ILIKE :keyword
                OR u.email ILIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }

        if (in_array($status, VerificationStatus::values(), true)) {
            $where[] = 'p.verification_status = :status';
            $params['status'] = $status;
        }

        $clause = implode(' AND ', $where);

        return $this->paginate(
            'SELECT count(*) AS total FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE ' . $clause,
            self::SELECT . ' WHERE ' . $clause . ' ORDER BY p.created_at DESC, p.id DESC',
            $params,
            max(1, $page),
            $perPage,
            '/admin/penyedia',
            array_filter(['q' => $keyword, 'status' => $status], static fn (string $value): bool => $value !== ''),
        );
    }

    public function listForAdmin(): array
    {
        return $this->database->select(
            self::SELECT . ' ORDER BY p.organization_name ASC, p.id ASC',
        );
    }

    public function findVerifiedById(int $id): ?array
    {
        return $this->database->selectOne(
            self::SELECT . " WHERE p.id = :id AND p.verification_status = 'verified'
                AND u.email NOT ILIKE '%.invalid'",
            ['id' => $id],
        );
    }

    public function paginatePublicDirectory(?string $keyword = null, int $perPage = 12): Paginator
    {
        $where = "p.verification_status = 'verified' AND u.email NOT ILIKE '%.invalid'";
        $params = [];
        $keyword = trim((string) $keyword);

        if ($keyword !== '') {
            $where .= ' AND (p.organization_name ILIKE :keyword OR p.campus_name ILIKE :keyword
                OR p.description ILIKE :keyword)';
            $params['keyword'] = '%' . $keyword . '%';
        }

        return $this->paginate(
            'SELECT count(*) AS total FROM provider_profiles p JOIN users u ON u.id = p.user_id WHERE ' . $where,
            self::SELECT . ' WHERE ' . $where . ' ORDER BY p.organization_name ASC',
            $params,
            1,
            $perPage,
            '/penyelenggara',
            $keyword !== '' ? ['q' => $keyword] : [],
        );
    }

    public function findVerifiedBySlug(string $slug): ?array
    {
        $rows = $this->database->select(
            self::SELECT . " WHERE p.verification_status = 'verified'
                AND u.email NOT ILIKE '%.invalid' ORDER BY p.organization_name ASC",
        );
        $generator = new SlugGenerator();

        foreach ($rows as $row) {
            if ($generator->base((string) $row['organization_name']) === $slug) {
                return $row;
            }
        }

        return null;
    }

    public function update(int $id, array $attributes): array
    {
        if (isset($attributes['verification_status'])) {
            $status = VerificationStatus::from((string) $attributes['verification_status']);

            if ($status !== VerificationStatus::Verified) {
                $attributes['verified_at'] = null;
                $attributes['verified_by'] = null;
            } elseif (! isset($attributes['verified_at'])) {
                $attributes['verified_at'] = $this->now();
            }
        }

        return $this->updateById('provider_profiles', $id, $attributes, [
            'organization_name', 'campus_name', 'official_email', 'website_url', 'description',
            'verification_status', 'verification_document_path', 'verification_note',
            'verified_at', 'verified_by',
        ]) ?? throw new \RuntimeException('Profil penyedia tidak ditemukan.');
    }

    public function countAll(): int
    {
        return (int) ($this->database->selectOne(
            "SELECT count(*) AS total FROM provider_profiles p JOIN users u ON u.id = p.user_id
             WHERE u.email NOT ILIKE '%.invalid'",
        )['total'] ?? 0);
    }

    public function countByVerificationStatus(): array
    {
        $rows = $this->database->select(
            "SELECT p.verification_status, count(*) AS total
             FROM provider_profiles p JOIN users u ON u.id = p.user_id
             WHERE u.email NOT ILIKE '%.invalid' GROUP BY p.verification_status",
        );
        $counts = array_fill_keys(VerificationStatus::values(), 0);

        foreach ($rows as $row) {
            $counts[(string) $row['verification_status']] = (int) $row['total'];
        }

        return $counts;
    }
}
