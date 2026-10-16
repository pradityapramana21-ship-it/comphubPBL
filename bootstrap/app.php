<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureProviderIsVerified;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RecordEventView;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |--------------------------------------------------------------------------
        | Alias middleware
        |--------------------------------------------------------------------------
        |
        | Alias supaya route tidak perlu mengimpor kelas lengkap. Nama alias
        | adalah "kontrak" yang dibaca anggota tim lain, jadi ditulis singkat
        | tapi jelas.
        |
        | PENTING: Middleware::alias() MENIMPAL, bukan menggabungkan. Semua
        | alias harus berada dalam satu array. Dua panggilan terpisah akan membuat
        | yang kedua menghapus yang pertama — dan setiap route yang memakai alias
        | hilang akan gagal dengan "Target class [role] does not exist".
        |
        */
        $middleware->alias([
            // Peran. Default deny: role yang tidak dikenal tidak lolos.
            'role' => EnsureRole::class,

            // Penyedia yang organisasinya sudah diverifikasi admin.
            'verified' => EnsureProviderIsVerified::class,

            // Mencatat tayangan kegiatan, dengan rate limiter per IP.
            'records.view' => RecordEventView::class,

            // Basic auth sengaja dinonaktifkan. Basic auth mengirim
            // kredensial di setiap permintaan dan tidak cocok untuk
            // aplikasi berbasis cookie seperti ini.
            'auth.basic' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Middleware global
        |--------------------------------------------------------------------------
        |
        | active_accounts dipasang untuk seluruh rute web, termasuk yang
        | publik. Alasannya: `auth` hanya memeriksa sesi, dan akun yang
        | ditangguhkan masih punya sesi yang sah — jadi tanpa ini orang
        | yang dinonaktifkan bisa menjelajah sampai sesinya berakhir.
        |
        */
        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);

        $middleware->trustProxies(at: '*');

        $middleware->preventRequestsDuringMaintenance();

        /*
        |--------------------------------------------------------------------------
        | Halaman tujuan redirect
        |--------------------------------------------------------------------------
        |
        | Tanpa baris ini, middleware auth bawaan Laravel mengalihkan ke
        | route bernama "login" — yang tidak ada di aplikasi ini karena
        | penamaannya memakai prefiks ("auth.login"). Akibatnya pengunjung
        | yang belum masuk mendapat error 500, bukan halaman masuk.
        |
        | `redirectUsersTo` berlaku sebaliknya: orang yang sudah login
        | tetapi membuka halaman masuk atau daftar.
        |
        */
        $middleware->redirectGuestsTo(fn (Request $request) => route('auth.login'));

        $middleware->redirectUsersTo(fn (Request $request) => route('user.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
        |--------------------------------------------------------------------------
        | Halaman kesalahan
        |--------------------------------------------------------------------------
        |
        | Halaman 503 lalu 429 supaya tamu tidak melihat halaman kosong.
        | Halaman lain memakai view di resources/views/errors/.
        |
        */
        $exceptions->render(function (\Illuminate\Http\Request $request, \Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return null;
        });
    })->create();