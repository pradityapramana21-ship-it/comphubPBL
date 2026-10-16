<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Akses data akun. Controller tidak boleh memanggil User::query() langsung —
 * dia harus lewat interface ini supaya aturan yang sama berlaku di mana saja.
 */
interface UserRepository
{
    public function create(string $name, string $email, string $password, UserRole $role): User;

    public function findById(int $id): ?User;

    /** Tidak ditemukan berarti akun tidak ada ATAU ditangguhkan. Pesannya sama untuk keduanya. */
    public function findActiveByEmail(string $email): ?User;

    public function findActiveById(int $id): ?User;

    public function emailExists(string $email, ?int $exceptId = null): bool;

    public function updateProfile(int $id, array $attributes): User;

    public function updateStatus(int $id, AccountStatus $status, ?string $note = null): User;

    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function countByRole(): array;
}
