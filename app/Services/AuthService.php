<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registrasi, masuk, keluar, dan ganti kata sandi.
 *
 * Dua keputusan yang disengaja:
 *
 * 1. Role tidak pernah dibaca dari input formulir. Daftar akun selalu
 *    membuat pengguna biasa; jalur pendaftaran penyedia punya metodenya
 *    sendiri. Kalau tidak, siapa pun bisa menulis role=admin di form.
 *
 * 2. Pesan gagal login tidak pernah membocorkan apakah sebuah email
 *    terdaftar (Panduan FR-01).
 */
class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ProviderProfileRepository $providers,
        private readonly AuditLogService $audit,
    ) {}

    /** Daftar sebagai pengguna biasa. */
    /**
     * Pendaftaran mahasiswa, INCLUDING login.
     *
     * Kalau akun dibuat tapi tidak langsung masuk, orang yang baru
     * mendaftar akan diarahkan ke dasbor lalu langsung dikembalikan ke
     * halaman masuk dengan pesan "sesi berakhir" — padahal tidak ada
     * yang salah. Jadi setelah akun dibuat, sesi dibuatkan dari awal.
     */
    public function registerUser(string $name, string $email, string $password): User
    {
        $normalised = mb_strtolower(trim($email));

        if ($this->users->emailExists($normalised)) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah terdaftar. Silakan masuk.',
            ]);
        }

        $user = $this->users->create($name, $normalised, $password, UserRole::User);

        $this->establishSession($user);

        $this->audit->record('user.registered', $user);

        return $user;
    }

    /**
     * Membuat sesi login untuk akun yang baru saja dibuat atau diverifikasi.
     */
    private function establishSession(User $user): void
    {
        Auth::login($user);

        // ID sesi baru menutup serangan session fixation, sama seperti
        // pada login biasa.
        session()->regenerate();
    }

    /**
     * Daftar sebagai penyedia kegiatan.
     *
     * Akun DAN profil organisasi dibuat dalam satu transaksi: setengah
     * terdaftar berarti akun yang masuk tapi tidak bisa apa-apa.
     */
    public function registerProvider(array $data, ?string $documentPath = null): User
    {
        $normalised = mb_strtolower(trim($data['email']));

        if ($this->users->emailExists($normalised)) {
            throw ValidationException::withMessages([
                'email' => 'Email ini sudah terdaftar. Silakan masuk.',
            ]);
        }

        return DB::transaction(function () use ($data, $normalised, $documentPath): User {
            $user = $this->users->create(
                name: $data['name'],
                email: $normalised,
                password: $data['password'],
                role: UserRole::Provider,
            );

            $this->providers->create([
                'user_id' => $user->id,
                'organization_name' => $data['organization_name'],
                'campus_name' => $data['campus_name'],
                'official_email' => mb_strtolower(trim($data['official_email'])),
                'website_url' => $data['website_url'] ?? null,
                'description' => $data['description'] ?? null,
                'verification_document_path' => $documentPath,
            ]);

            $this->establishSession($user);

            $this->audit->record('provider.registered', $user);

            return $user;
        });
    }

    /**
     * Masuk.
     *
     * Akun yang ditangguhkan menghasilkan pesan yang PERSIS sama dengan
     * email yang tidak terdaftar. Kalau berbeda, penyerang bisa memakai
     * halaman ini untuk mencari tahu email mana yang terdaftar.
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, bool $remember = false): User
    {
        $user = $this->users->findActiveByEmail($email);

        if ($user === null || ! Auth::validate(['email' => mb_strtolower(trim($email)), 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        // Dua pemeriksaan terpisah: peran tetap ada, tapi status menentukan
        // apakah boleh masuk sama sekali.
        if ($user->account_status !== AccountStatus::Active) {
            throw ValidationException::withMessages([
                'email' => 'Akun ini sedang ditangguhkan. Hubungi administrator.',
            ]);
        }

        Auth::login($user, $remember);

        // ID sesi baru setelah login menutup serangan session fixation.
        session()->regenerate();

        $this->audit->record('user.logged_in', $user);

        return $user;
    }

    public function logout(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->audit->record('user.logged_out', $user);
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Auth::validate(['email' => $user->email, 'password' => $currentPassword])) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak cocok.',
            ]);
        }

        $user->forceFill(['password' => $newPassword])->save();

        // Sesi lain ikut gugur supaya kata sandi lama tidak tetap berlaku
        // di perangkat yang sebelumnya sempat login.
        $this->purgeOtherSessions($user);

        $this->audit->record('user.password_changed', $user);
    }

    /**
     * Hapus baris sesi milik pengguna ini kecuali yang sedang aktif.
     */
    private function purgeOtherSessions(User $user): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when(config('session.driver') === 'database', function ($query): void {
                $currentId = session()->getId();

                if ($currentId !== null) {
                    $query->where('id', '!=', $currentId);
                }
            })
            ->delete();
    }

    /** Profil organisasi milik pengguna — dipakai middleware dan view. */
    public function profileFor(User $user): ?ProviderProfile
    {
        return $this->providers->findByUserId($user->id);
    }
}
