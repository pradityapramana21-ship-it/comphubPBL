<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute ke satu atau beberapa role.
 *
 * Dipakai sebagai `role:admin` atau `role:provider,admin` di route.
 * Default-nya menolak: role yang tidak dikenal TIDAK diberi akses.
 *
 * Menyembunyikan tombol di HTML bukan kontrol keamanan — pemeriksaan
 * di sini adalah lapisan yang benar-benar ditegakkan server
 * (Panduan §2, OWASP Authorization).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Belum masuk.'], 401)
                : redirect()->guest(route('auth.login'));
        }

        $allowed = array_map(
            static fn (string $role): UserRole => UserRole::from($role),
            $roles,
        );

        if (! in_array($user->role, $allowed, true)) {
            // 403, bukan 404. Halaman memang ada, tapi akses Anda tidak
            // diizinkan — dan pesan 403 lebih berguna untuk pengguna
            // daripada "tidak ditemukan" yang menyesatkan.
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
