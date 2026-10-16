<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan pengguna yang ditangguhkan.
 *
 * Middleware `auth` hanya memeriksa "sudah login atau belum". Akun yang
 * ditangguhkan masih punya sesi yang valid, jadi tanpa middleware ini
 * Orang yang dinonaktifkan masih bisa menjelajah situs sampai sesinya
 * berakhir.
 *
 * Sesi ikut diputus supaya orang yang ditangguhkan tidak bisa melewati
 * pemeriksaan hanya dengan memuat ulang halaman.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && $user->account_status !== AccountStatus::Active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('auth.login')
                ->withErrors([
                    'email' => 'Akun Anda sedang ditangguhkan. Hubungi administrator untuk informasi lebih lanjut.',
                ]);
        }

        return $next($request);
    }
}
