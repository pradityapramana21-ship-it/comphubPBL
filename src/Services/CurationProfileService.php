<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CurationProfileService
{
    private ?array $profile = null;

    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string, mixed> */
    public function profile(): array
    {
        if ($this->profile !== null) {
            return $this->profile;
        }

        $profile = $this->database->selectOne(
            "SELECT p.* FROM provider_profiles p
             JOIN users u ON u.id = p.user_id
             WHERE p.organization_name = :name AND p.verification_status = 'verified'
             ORDER BY p.id ASC LIMIT 1",
            ['name' => 'Tim Kurasi CompHub'],
        );

        if ($profile === null) {
            throw new \RuntimeException('Profil kurasi belum tersedia.');
        }

        return $this->profile = $profile;
    }

    public function id(): int
    {
        return (int) $this->profile()['id'];
    }
}
