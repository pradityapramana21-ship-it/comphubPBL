<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepository
{
    public function create(string $name, string $email, string $password, UserRole $role): User
    {
        return User::create([
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
            'role' => $role,
            'account_status' => AccountStatus::Active,
        ]);
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findActiveByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->active()
            ->first();
    }

    public function findActiveById(int $id): ?User
    {
        return User::query()->whereKey($id)->active()->first();
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $email = mb_strtolower(trim($email));

        return User::query()
            ->where('email', $email)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function updateProfile(int $id, array $attributes): User
    {
        $user = User::findOrFail($id);
        $user->fill($attributes)->save();

        return $user->refresh();
    }

    public function updateStatus(int $id, AccountStatus $status, ?string $note = null): User
    {
        $user = User::findOrFail($id);

        $user->forceFill([
            'account_status' => $status,
            'suspended_at' => $status === AccountStatus::Suspended ? now() : null,
            'suspension_note' => $status === AccountStatus::Suspended ? $note : null,
        ])->save();

        return $user->refresh();
    }

    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $sortMap = [
            'terbaru' => 'created_at',
            'nama' => 'name',
            'peran' => 'role',
        ];
        $column = $sortMap[$filters['sort'] ?? ''] ?? 'created_at';

        return User::query()
            ->when($filters['keyword'] ?? null, function (Builder $q, string $term): void {
                $term = trim($term);
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%"));
            })
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->where('role', $role))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('account_status', $s))
            ->orderBy($column, 'desc')
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    public function countByRole(): array
    {
        return User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();
    }
}
