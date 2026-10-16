<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Cache\RateLimiting\Limit;
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
        $limits = $this->limits();

        if ($limits === []) {
            return;
        }

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                throw ValidationException::withMessages([
                    'email' => sprintf(
                        'Terlalu banyak percobaan masuk. Coba lagi dalam %d detik.',
                        RateLimiter::availableIn($limit->key),
                    ),
                ]);
            }
        }
    }

    /**
     * Catat percobaan, berhasil maupun gagal.
     *
     * Setiap limit dipanggil dengan decay-nya sendiri. Kalau tidak,
     * batas "5 kali dalam 15 menit" akan terhitung sebagai "5 kali dalam
     * 1 menit" karena itu nilai bawaan RateLimiter::hit().
     */
    public function recordAttempt(): void
    {
        foreach ($this->limits() as $limit) {
            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }
    }

    /** Berapa detik lagi orang boleh mencoba lagi. */
    public function secondsUntilAvailable(): int
    {
        $limits = $this->limits();

        if ($limits === []) {
            return 0;
        }

        return max(array_map(
            static fn (Limit $limit): int => RateLimiter::availableIn($limit->key),
            $limits,
        ));
    }

    /**
     * Batas-batas yang berlaku untuk permintaan ini.
     *
     * Limiter 'login' sengaja mendaftarkan dua batas sekaligus: satu per
     * email dan satu per alamat IP. Keduanya harus dihormati, jadi hasil
     * limiter dinormalkan menjadi daftar. Limiter yang hanya mengembalikan
     * satu Limit pun tetap bekerja karena dibungkus jadi daftar berisi satu.
     *
     * @return list<Limit>
     */
    private function limits(): array
    {
        $limiter = RateLimiter::limiter('login');

        if ($limiter === null) {
            return [];
        }

        $result = $limiter($this);

        return is_array($result) ? array_values($result) : [$result];
    }

    /** Email sudah dinormalisasi, siap dipakai sebagai kunci. */
    public function normalisedEmail(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }
}
