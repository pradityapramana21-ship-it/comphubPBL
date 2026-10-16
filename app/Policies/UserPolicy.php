<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Otorisasi akun.
 *
 * Dua lapis berbeda: apakah boleh MENYENTUH akun, dan apakah boleh
 * MENGGANTIKAN perannya. Admin boleh menangguhkan akun biasa, tapi tidak
 * boleh menyentuh akun admin lain — supaya tidak ada admin tunggal yang
 * bisa mengunci seluruh sistem, dan setiap tindakan selalu punya minimal satu
 * saksi.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessApplication() && $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id || $this->mayManage($user, $target);
    }

    /** Menangguhkan/mengaktifkan akun. */
    public function manageStatus(User $user, User $target): bool
    {
        return $this->mayManage($user, $target);
    }

    public function changeRole(User $user, User $target): bool
    {
        return $this->mayManage($user, $target) && $user->id !== $target->id;
    }

    private function mayManage(User $actor, User $target): bool
    {
        if (! $actor->canAccessApplication() || ! $actor->isAdmin()) {
            return false;
        }

        // Admin boleh mengelola akun provider dan user biasa, tapi
        // TIDAK BOLEH menyentuh akun admin lain.
        // Kalau ini diizinkan, satu akun yang berhasil diretas bisa
        // mengunci seluruh sistem dalam satu klik.
        return $target->role !== UserRole::Admin && $actor->id !== $target->id;
    }
}
