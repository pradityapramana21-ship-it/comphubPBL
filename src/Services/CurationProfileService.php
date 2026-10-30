<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CurationProfileService
{
    private const EMAIL = 'kurasi@internal.invalid';

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

        $profile = $this->find();

        if ($profile === null) {
            $this->ensure();
            $profile = $this->find();
        }

        if ($profile === null) {
            throw new \RuntimeException('Profil kurasi belum tersedia.');
        }

        return $this->profile = $profile;
    }

    public function id(): int
    {
        return (int) $this->profile()['id'];
    }

    /** @return array<string, mixed>|null */
    private function find(): ?array
    {
        return $this->database->selectOne(
            "SELECT p.* FROM provider_profiles p
             JOIN users u ON u.id = p.user_id
             WHERE p.organization_name = :name AND p.verification_status = 'verified'
             ORDER BY p.id ASC LIMIT 1",
            ['name' => 'Tim Kurasi CompHub'],
        );
    }

    private function ensure(): void
    {
        $this->database->transaction(function (): void {
            $user = $this->database->selectOne('SELECT * FROM users WHERE email = :email', ['email' => self::EMAIL]);

            if ($user === null) {
                $user = $this->database->selectOne(
                    "INSERT INTO users (name, email, password, role, account_status, suspended_at, suspension_note)
                     VALUES (:name, :email, :password, 'provider', 'suspended', now(), :note)
                     RETURNING *",
                    [
                        'name' => 'Tim Kurasi CompHub',
                        'email' => self::EMAIL,
                        'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                        'note' => 'Akun sistem kurasi. Tidak untuk login.',
                    ],
                );
            }

            $this->database->execute(
                "UPDATE users SET account_status = 'suspended', suspended_at = COALESCE(suspended_at, now()),
                    suspension_note = :note, updated_at = now() WHERE id = :id",
                ['note' => 'Akun sistem kurasi. Tidak untuk login.', 'id' => (int) $user['id']],
            );

            $profile = $this->database->selectOne(
                'SELECT * FROM provider_profiles WHERE user_id = :user_id',
                ['user_id' => (int) $user['id']],
            );

            if ($profile === null) {
                $this->database->execute(
                    "INSERT INTO provider_profiles
                        (user_id, organization_name, campus_name, official_email, description,
                         verification_status, verified_at)
                     VALUES (:user_id, :name, :campus, :email, :description, 'verified', now())",
                    [
                        'user_id' => (int) $user['id'],
                        'name' => 'Tim Kurasi CompHub',
                        'campus' => 'Lintas kampus',
                        'email' => self::EMAIL,
                        'description' => 'Profil sistem untuk kegiatan yang diajukan publik.',
                    ],
                );
            } else {
                $this->database->execute(
                    "UPDATE provider_profiles SET organization_name = :name, verification_status = 'verified',
                        verified_at = COALESCE(verified_at, now()), updated_at = now() WHERE id = :id",
                    ['name' => 'Tim Kurasi CompHub', 'id' => (int) $profile['id']],
                );
            }
        });
    }
}
