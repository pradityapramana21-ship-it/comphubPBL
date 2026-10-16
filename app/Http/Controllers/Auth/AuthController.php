<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DataTransferObjects\CatalogFilter;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ProviderRegistrationRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Services\ProviderVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Masuk, keluar, dan pendaftaran.
 *
 * Ada DUA jalur pendaftaran, dan itu disengaja:
 *
 *  1. /register             -> pengguna biasa (mencari kegiatan)
 *  2. /register/provider    -> penyelenggara kegiatan (mempublish)
 *
 * Satu form dengan pilihan "mau jadi apa pun" akan tampak ramah, tapi
 * justru membuka celah: akun dan peran ikut menentukan siapa yang boleh
 * menyetujui kegiatan. Dua jalur terpisah membuat peran tidak
 * pernah datang dari pilihan yang bisa dimanipulasi.
 */
class AuthController extends Controller
{
    public function showLogin(): \Illuminate\View\View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request, AuthService $auth): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        try {
            $user = $auth->login(
                email: $request->normalisedEmail(),
                password: (string) $request->input('password'),
                remember: $request->boolean('remember'),
            );
        } catch (ValidationException $e) {
            // Percobaan GAGAL ikut dihitung. Kalau tidak, penyerang bisa
            // menebak tanpa batas selama limiter hanya menghitung yang lupa
            // password.
            $request->recordFailure();

            throw $e;
        }

        $request->clearFailures();

        return $this->redirectAfterLogin($request, $user);
    }

    public function logout(Request $request, AuthService $auth): RedirectResponse
    {
        $auth->logout();

        return redirect()->route('home')->with('success', 'Anda sudah keluar.');
    }

    // ------------------------------------------------------------ pengguna

    public function showRegister(): \Illuminate\View\View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request, AuthService $auth): RedirectResponse
    {
        $user = $auth->registerUser(
            name: (string) $request->input('name'),
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
        );

        // Sesi sudah dibuat AuthService::registerUser(); regenerate di
        // sini hanya mengulang pekerjaan yang sama.
        return redirect()
            ->route('user.dashboard')
            ->with('success', 'Selamat datang, '.$user->name.'! Simpan kegiatan yang menarik agar tidak terlewat.');
    }

    // ------------------------------------------------------------ penyedia

    public function showProviderRegister(): \Illuminate\View\View
    {
        return view('auth.register-provider');
    }

    public function registerProvider(
        ProviderRegistrationRequest $request,
        AuthService $auth,
        ProviderVerificationService $verification,
    ): RedirectResponse {
        // Berkas disimpan DI LUAR webroot. Nama berkas diacak supaya
        // nama aslinya tidak terpotret di URL mana pun (Panduan §8).
        $path = null;

        if ($request->hasFile('verification_document')) {
            $path = $request->file('verification_document')->store(
                'uploads/verification',
                'local',
            );
        }

        $auth->registerProvider($request->providerAttributes(), $path);

        return redirect()
            ->route('provider.dashboard')
            ->with(
                'success',
                $path === null
                    ? 'Pendaftaran berhasil. Data organisasi Anda akan diperiksa administrator.'
                    : 'Pendaftaran berhasil. Dokumen Anda sudah diterima dan menunggu pemeriksaan administrator.',
            );
    }

    /**
     * Ke mana pengguna dikirim setelah masuk.
     *
     * Disesuaikan dengan perannya, bukan dengan halaman yang biasanya
     * dibuka — supaya admin yang menguji tautan lama tidak mendarat di
     * halaman pengguna.
     */
    private function redirectAfterLogin(LoginRequest $request, \App\Models\User $user): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isSafeRedirect($intended)) {
            return redirect()->to($intended);
        }

        return match ($user->role) {
            UserRole::Admin => redirect()->route('admin.dashboard'),
            UserRole::Provider => $user->hasVerifiedProvider()
                ? redirect()->route('provider.dashboard')
                : redirect()->route('provider.dashboard')->with(
                    'status',
                    'Organisasi Anda belum terverifikasi. Anda bisa menyimpan draf, tetapi belum bisa mengajukan.',
                ),
            default => redirect()->intended(route('user.dashboard')),
        };
    }

    /**
     * Cegah open redirect: hanya terima tujuan di dalam aplikasi ini.
     *
     * Tanpa pemeriksaan ini, penyerang bisa mengirim tautan
     * /login?next=https:// situs-palsu.example dan memakai halaman masuk kita
     * sebagai pengarah.
     */
    private function isSafeRedirect(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }
}
