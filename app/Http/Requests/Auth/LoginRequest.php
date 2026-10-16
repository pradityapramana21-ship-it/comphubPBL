<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validasi dan pembatasan laju untuk halaman masuk.
 *
 * Dua lapis pembatasan ada di sini, dan keduanya disengaja:
 *
 *  - `throttle:login` (middleware) membatasi VOLUME permintaan, sehingga
 *    percobaan otomatis yang berpindah-pindah kata sandi akan dihentikan
 *    oleh middleware, bukan oleh kode halaman ini.
 *  - Penghitung di bawah ini membatasi KEGAGALAN, dan bisa dikosongkan
 *    begitu login berhasil.
 */
class LoginRequest extends FormRequest
{
    /** Lima kali gagal dalam lima belas menit sudah lebih dari cukup. */
    private const FAILURE_LIMIT = 5;

    private const FAILURE_DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            // Tidak ada aturan min di sini. Aturan minimum ada di
            // AuthService::register(); di halaman login, pesan "kata sandi
            // terlalu pendek" justru membocorkan kebijakan sistem.
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => 'email',
            'password' => 'kata sandi',
        ];
    }

    /**
     * Pastikan percobaan GAGAL belum melewati batas.
     *
     * Penghitung ini milik aplikasi sendiri, terpisah dari middleware
     * `throttle:login`. Alasannya penting: middleware menyusun kunci
     * counternya sendiri secara internal (`login:<kunci>` atau md5-nya),
     * jadi aplikasi tidak bisa membaca atau mengosongkan penghitung yang
     * sama. Percobaan yang benar-benar gagal needing dik Vegas dari
     * request volume.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->failedAttemptsKey(), self::FAILURE_LIMIT)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Terlalu banyak percobaan masuk yang gagal. Coba lagi dalam %d detik.',
                RateLimiter::availableIn($this->failedAttemptsKey()),
            ),
        ]);
    }

    /**
     * Catat percobaan gagal.
     */
    public function recordFailure(): void
    {
        RateLimiter::hit($this->failedAttemptsKey(), self::FAILURE_DECAY_SECONDS);
    }

    /** Berapa detik lagi orang boleh mencoba lagi. */
    public function secondsUntilAvailable(): int
    {
        return RateLimiter::availableIn($this->failedAttemptsKey());
    }

    /**
     * Hapus jejak kegagalan setelah login berhasil.
     *
     * Tanpa ini, orang yang salah ketik tiga kali lalu berhasil masuk
     * akan terkunci pada percobaan berikutnya, padahal dia sudah
     * membuktikan kata sandinya benar.
     */
    public function clearFailures(): void
    {
        RateLimiter::clear($this->failedAttemptsKey());
    }

    /**
     * Kunci penghitung kegagalan.
     *
     * Namespace-nya sengaja berbeda dari "login" milik middleware,
     * supaya tidak pernah saling menimpa.
     */
    private function failedAttemptsKey(): string
    {
        return 'login-failed:'.$this->normalisedEmail().'|'.$this->ip();
    }

    /** Email sudah dinormalisasi, siap dipakai sebagai kunci. */
    public function normalisedEmail(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }
}
