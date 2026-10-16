<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;

/**
 * Otorisasi kegiatan.
 *
 * Prinsip: default DENY. Setiap metode mengembalikan false lebih dulu,
 * baru membuka jalan kalau syaratnya benar-benar terpenuhi. Cara ini
 * membuat kasus baru aman secara bawaan, bukan harus diingat setiap kali
 * menambah fitur (Panduan §2, OWASP Authorization).
 */
class EventPolicy
{
    /** Admin sudah punya kuota approve/reject lewat antrean. */
    public function viewAny(User $user): bool
    {
        return $user->canAccessApplication();
    }

    /** Halaman detail publik tidak lewat policy — publik tidak login. */
    public function view(?User $user, Event $event): bool
    {
        // Terdaftar di publik: hanya approved. Admin: semua. Pemilik: Punishment miliknya.
        if ($user?->isAdmin() === true) {
            return true;
        }

        if ($event->isPublished()) {
            return true;
        }

        return $user !== null && $this->ownsProfile($user, $event);
    }

    public function create(User $user): bool
    {
        return $user->canAccessApplication()
            && $user->isProvider()
            && $user->hasVerifiedProvider();
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Pemilik boleh mengubah, TAPI hanya kegiatan yang belum tayang.
        // Kegiatan yang sudah approved harus diminta tinjau ulang lewat revisi,
        // supaya tidak ada jalan pintas melewati moderasi.
        return $this->ownsProfile($user, $event)
            && $event->isEditableByProvider();
    }

    public function submit(User $user, Event $event): bool
    {
        return $user->canAccessApplication()
            && $this->ownsProfile($user, $event)
            && in_array($event->status->value, ['draft', 'changes_requested', 'rejected'], true);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->ownsProfile($user, $event) && $event->isEditableByProvider();
    }

    /**
     * Menyetujui, menolak, meminta perbaikan — hanya admin, dan provider
     * tidak pernah bisa menyetujui karyanya sendiri (Panduan §2).
     */
    public function moderate(User $user, Event $event): bool
    {
        return $user->canAccessApplication() && $user->role === UserRole::Admin;
    }

    public function archive(User $user, Event $event): bool
    {
        return $this->moderate($user, $event) && ! $event->status->isTerminal();
    }

    public function bookmark(User $user, Event $event): bool
    {
        return $user->canAccessApplication()
            && $event->isPublished()
            && $event->registration_deadline->isFuture();
    }

    public function report(User $user, Event $event): bool
    {
        return $user->canAccessApplication()
            && $event->isPublished()
            && ! $this->ownsProfile($user, $event);
    }

    /** Pemilik tidak bisa melapor karyanya sendiri — hanya orang lain. */
    private function ownsProfile(User $user, Event $event): bool
    {
        return $user->providerProfile !== null
            && $event->provider_profile_id === $user->providerProfile->id;
    }
}
