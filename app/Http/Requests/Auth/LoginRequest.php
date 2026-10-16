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
 * Rate limiter sudah didaftarkan dengan nama 'login' di
 * AppServiceProvider. Halaman ini tinggal memakainya.
 */
class LoginRequest extends FormRequest
{
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
     * Pastikan permintaan ini belum melewati batas percobaan.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $limiter = RateLimiter::limiter('login');

        if ($limiter === null) {
            return;
        }

        $limit = $limiter($this);

        if (! RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Terlalu banyak percobaan masuk. Coba lagi dalam %d detik.',
                RateLimiter::availableIn($limit->key),
            ),
        ]);
    }

    /**
     * Catat percobaan, berhasil maupun gagal.
     */
    public function recordAttempt(): void
    {
        $limiter = RateLimiter::limiter('login');

        if ($limiter !== null) {
            RateLimiter::hit($limiter($this)->key);
        }
    }

    /** Berapa detik lagi orang boleh mencoba lagi. */
    public function secondsUntilAvailable(): int
    {
        $limiter = RateLimiter::limiter('login');

        return $limiter === null ? 0 : RateLimiter::availableIn($limiter($this)->key);
    }

    /** Email sudah dinormalisasi, siap dipakai sebagai kunci. */
    public function normalisedEmail(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }
}
