<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Lupa kata sandi dan atur ulang.
 *
 * Dua hal yang sering terlewat dan keduanya sudah ditangani di sini:
 *
 *  1. Halaman "kirim email" SELALU melaporkan berhasil, baik emailnya
 *     terdaftar atau bukan. Kalau pesan berubah sesuai ada/tidaknya
 *     email, halaman ini menjadi alat untuk mencari tahu siapa yang punya
 *     akun di situs ini.
 *
 *  2. Token hanya berlaku satu jam dan dihapus setelah dipakai.
 */
class PasswordResetController extends Controller
{
    public function showRequest(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
        ]);

        // Pesan yang SAMA untuk kedua kemungkinan. Variabel $status sengaja
        // tidak dipakai dalam pesan; kalau berbeda, halaman ini jadi alat
        // untuk mencari tahu email mana yang terdaftar di situs ini.
        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            'Jika email itu terdaftar, tautan atur ulang sudah dikirim. Periksa kotak masuk dan folder spam.',
        );
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    // Memaksa logout di semua perangkat lain.
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Tautan atur ulang sudah tidak berlaku atau sudah pernah dipakai. Minta tautan baru.',
            ]);
        }

        return redirect()
            ->route('auth.login')
            ->with('success', 'Kata sandi berhasil diperbarui. Silakan masuk.');
    }
}
