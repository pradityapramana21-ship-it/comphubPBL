<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses ke area penyedia yang sudah terverifikasi.
 *
 * Menyusun kegiatan itu tetap boleh untuk yang belum verifikasi — mereka
 * butuh tempat menyimpan draf. Yang dikunci adalah pengajuan dan
 * dashboard statistik, karena keduanya butuh kontak resmi yang sudah
 * diperiksa.
 */
class EnsureProviderIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Belum masuk.'], 401)
                : redirect()->guest(route('auth.login'));
        }

        $profile = $user->providerProfile;

        if ($profile === null) {
            abort(403, 'Akun ini tidak terdaftar sebagai penyedia kegiatan.');
        }

        if ($profile->verification_status !== VerificationStatus::Verified) {
            return redirect()
                ->route('provider.profile.edit')
                ->with('status', match ($profile->verification_status) {
                    VerificationStatus::Pending => 'Organisasi Anda sedang menunggu verifikasi administrator.',
                    VerificationStatus::Rejected => 'Verifikasi ditolak. Perbaiki data organisasi lalu ajukan ulang.',
                    default => 'Organisasi Anda belum terverifikasi.',
                });
        }

        return $next($request);
    }
}
