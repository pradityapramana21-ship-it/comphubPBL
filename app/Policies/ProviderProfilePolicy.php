<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProviderProfile;
use App\Models\User;

/**
 * Otorisasi profil organisasi.
 *
 * Verifikasi hanya admin. Penyedia boleh mengubah profilnya sendiri, tapi
 * TIDAK boleh menyentuh kolom verifikasi — kolom itu bahkan tidak mass-
 * assignable (lihat ProviderProfile).
 */
class ProviderProfilePolicy
{
    public function view(?User $user, ProviderProfile $profile): bool
    {
        if ($profile->isVerified()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $user->isAdmin() || $this->owns($user, $profile);
    }

    public function update(User $user, ProviderProfile $profile): bool
    {
        return $user->canAccessApplication() && $this->owns($user, $profile);
    }

    public function verify(User $user, ProviderProfile $profile): bool
    {
        return $user->canAccessApplication() && $user->isAdmin();
    }

    /** Menu "Ajukan lagi verifikasi" hanya untuk yang ditolak. */
    public function resubmit(User $user, ProviderProfile $profile): bool
    {
        return $user->canAccessApplication() && $this->owns($user, $profile);
    }

    private function owns(User $user, ProviderProfile $profile): bool
    {
        return $profile->user_id === $user->id;
    }
}
