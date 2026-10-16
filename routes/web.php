<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\EventModerationController;
use App\Http\Controllers\Admin\ProviderVerificationController;
use App\Http\Controllers\Admin\ReportReviewController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Public\EventCatalogController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProviderDirectoryController;
use App\Http\Controllers\Public\StaticPageController;
use App\Http\Controllers\Provider\ProviderDashboardController;
use App\Http\Controllers\Provider\ProviderEventController;
use App\Http\Controllers\Provider\ProviderProfileController;
use App\Http\Controllers\User\BookmarkController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\ReportController;
use App\Http\Controllers\User\UserDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute web
|--------------------------------------------------------------------------
|
| Roti ini adalah KONTRAK antara tim, bukan daftar teknis. Nama rute,
| parameter, dan middleware di sini dibaca frontend, dokumentasi, dan
| test sekaligus. Mengubahnya berarti mengubah ketiganya — itu sebabnya
| nama rute tidak pernah ditulis sebagai string di dalam controller.
|
| ATURAN MEMBINDING PARAMETER:
|
|   Publik  : memakai slug, karena tautannya dibagikan ke orang.
|   Privat  : memakai ID EKSPLISIT ({event:id}), bukan slug.
|
| Alasan yang kedua penting. Kegiatan milik orang lain tetap punya
| slug, jadi kalau rute privat ikut memakai slug, mengembalikan 403
| membocarkan bahwa kegiatan itu ada. Dengan ID, rute privat selalu
| 404 kalau tidak ditemukan, dan tidak mengonfirmasi apa pun.
|
*/

Route::get('/', HomeController::class)->name('home');

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
*/

Route::get('/kegiatan', [EventCatalogController::class, 'index'])->name('events.index');
// {event} tanpa ":id" -> diikat memakai getRouteKeyName() Event, yaitu slug.
// Rute ini memakai binding yang sama dengan middleware records.view dan
// controller, sehingga ketiganya melihat objek yang sama persis.
Route::get('/kegiatan/{event}', [EventCatalogController::class, 'show'])
    ->middleware('records.view')
    ->name('events.show');

Route::get('/penyelenggara', [ProviderDirectoryController::class, 'index'])->name('providers.index');
Route::get('/penyelenggara/{slug}', [ProviderDirectoryController::class, 'show'])->name('providers.show');

Route::get('/tentang', [StaticPageController::class, 'about'])->name('pages.about');
Route::get('/kebijakan-moderasi', [StaticPageController::class, 'moderationPolicy'])->name('pages.moderation');
Route::get('/panduan-penyelenggara', [StaticPageController::class, 'providerGuide'])->name('pages.providerGuide');
Route::get('/kebijakan-privasi', [StaticPageController::class, 'privacy'])->name('pages.privacy');
Route::get('/kontak', [StaticPageController::class, 'contact'])->name('pages.contact');

/*
|--------------------------------------------------------------------------
| Menyimpan kegiatan — butuh masuk
|--------------------------------------------------------------------------
|
| POST, bukan GET. GET tidak boleh mengubah state: kalau tombolnya GET,
| <img src="..."> di halaman lain sudah cukup untuk menyimpan kegiatan
| milik orang yang sedang login.
|
*/

Route::middleware('auth')->group(function (): void {
    Route::post('/kegiatan/{event:id}/bookmark', [EventCatalogController::class, 'store'])
        ->middleware('throttle:bookmark')
        ->name('events.bookmark.store');

    Route::delete('/kegiatan/{event:id}/bookmark', [EventCatalogController::class, 'destroy'])
        ->middleware('throttle:bookmark')
        ->name('events.bookmark.destroy');
});

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/masuk', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login.attempt');

    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('auth.register');
    Route::post('/daftar', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('auth.register.store');

    Route::get('/daftar/penyelenggara', [AuthController::class, 'showProviderRegister'])->name('auth.registerProvider');
    Route::post('/daftar/penyelenggara', [AuthController::class, 'registerProvider'])
        ->middleware('throttle:register')
        ->name('auth.registerProvider.store');

    Route::get('/lupa-sandi', [PasswordResetController::class, 'showRequest'])->name('auth.password.request');
    Route::post('/lupa-sandi', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:login')
        ->name('auth.password.email');

    Route::get('/atur-sandi/{token}', [PasswordResetController::class, 'showReset'])->name('auth.password.reset');
    Route::post('/atur-sandi', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:login')
        ->name('auth.password.update');
});

Route::post('/keluar', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('auth.logout');

/*
|--------------------------------------------------------------------------
| Pengguna
|--------------------------------------------------------------------------
|
| role:user SANGAT ketat: pengguna biasa tidak boleh punya akses ke
| dashboard penyedia maupun admin. Memakai 'user' (bukan
| 'user,provider') supaya orang yang salah menekan tombol tidak berakhir
| di dasbor yang salah.
|
*/

Route::middleware(['auth', 'role:user'])->group(function (): void {
    Route::get('/dasbor', UserDashboardController::class)->name('user.dashboard');

    Route::get('/tersimpan', [BookmarkController::class, 'index'])->name('user.bookmarks.index');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('user.profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('user.profile.update');

    Route::get('/profil/sandi', [ProfileController::class, 'editPassword'])->name('user.profile.password.edit');
    Route::put('/profil/sandi', [ProfileController::class, 'updatePassword'])->name('user.profile.password.update');

    Route::get('/kegiatan/{event:id}/laporkan', [ReportController::class, 'create'])->name('user.reports.create');
    Route::post('/kegiatan/{event:id}/laporkan', [ReportController::class, 'store'])
        ->middleware('throttle:report')
        ->name('user.reports.store');
    Route::delete('/laporan/{report}', [ReportController::class, 'destroy'])->name('user.reports.destroy');
});

/*
|--------------------------------------------------------------------------
| Penyedia
|--------------------------------------------------------------------------
|
| Middleware verified TIDAK dipasang di seluruh grup. Alasannya sederhana:
| nama organisasi yang belum diperiksa tidak boleh ikut masuk ke halaman
| publik, dan halaman mana pun yang menampilkannya harus tetap bisa dibuka
| pemilik akunnya sendiri untuk memperbaikinya. Yang terkunci adalah
| pengajuan ke administrator dan statistik yang butuh kontak resmi.
|
*/

Route::middleware(['auth', 'role:provider'])
    ->prefix('dasbor/penyelenggara')
    ->group(function (): void {
        // PENTING: SELURUH area penyedia hidup di bawah /dasbor/...
        //
        // Dua alasan, keduanya soal rute publik yang dibuat lebih dulu:
        //   1. /penyelenggara/{slug} akan menangkap /penyelenggara/dasbor
        //      sebagai slug "dasbor" — pengunjung yang belum masuk lalu
        //      mendapat 404, bukan diarahkan ke halaman masuk.
        //   2. /kegiatan/{event} akan menangkap /kegiatan/saya sebagai slug
        //      "saya" — daftar kegiatan penyedia jadi tidak bisa dibuka sama
        //      sekali. Karena kedua rute publik terdaftar lebih dulu,
        //      pemindahannya tidak akan menolong;_prefiksnya yang harus beda.
        Route::get('/', ProviderDashboardController::class)
            ->name('provider.dashboard');

        Route::get('/profil-organisasi', [ProviderProfileController::class, 'edit'])
            ->name('provider.profile.edit');
        Route::put('/profil-organisasi', [ProviderProfileController::class, 'update'])
            ->name('provider.profile.update');

        Route::get('/kegiatan', [ProviderEventController::class, 'index'])->name('provider.events.index');
        Route::get('/kegiatan/baru', [ProviderEventController::class, 'create'])->name('provider.events.create');
        Route::post('/kegiatan', [ProviderEventController::class, 'store'])->name('provider.events.store');
        Route::get('/kegiatan/{event:id}/sunting', [ProviderEventController::class, 'edit'])->name('provider.events.edit');
        Route::put('/kegiatan/{event:id}', [ProviderEventController::class, 'update'])->name('provider.events.update');
        Route::get('/kegiatan/{event:id}', [ProviderEventController::class, 'show'])->name('provider.events.show');
        Route::delete('/kegiatan/{event:id}', [ProviderEventController::class, 'destroy'])->name('provider.events.destroy');

        // Pengajuan ke administrator: hanya setelah verifikasi.
        Route::middleware('verified')->group(function (): void {
            Route::post('/kegiatan/{event:id}/ajukan', [ProviderEventController::class, 'submit'])
                ->middleware('throttle:event-submit')
                ->name('provider.events.submit');
        });
    });

/*
|--------------------------------------------------------------------------
| Administrator
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    // Antrean tinjauan kegiatan.
    Route::get('/kegiatan', [EventModerationController::class, 'index'])->name('events.index');
    Route::get('/kegiatan/{event:id}', [EventModerationController::class, 'show'])->name('events.show');
    Route::post('/kegiatan/{event:id}/moderasi', [EventModerationController::class, 'moderate'])
        ->middleware('throttle:global')
        ->name('events.moderate');

    // Antrean verifikasi organisasi.
    Route::get('/penyelenggara', [ProviderVerificationController::class, 'index'])->name('providers.index');
    Route::post('/penyelenggara/{provider:id}/keputusan', [ProviderVerificationController::class, 'decide'])
        ->name('providers.decide');

    // Antrean laporan.
    Route::get('/laporan', [ReportReviewController::class, 'index'])->name('reports.index');
    Route::post('/laporan/{report}/tinjau', [ReportReviewController::class, 'open'])->name('reports.open');
    Route::post('/laporan/{report}/selesai', [ReportReviewController::class, 'resolve'])->name('reports.resolve');
    Route::post('/laporan/{report}/tidak-diteruskan', [ReportReviewController::class, 'dismiss'])->name('reports.dismiss');

    // Manajemen akun.
    Route::get('/pengguna', [UserManagementController::class, 'index'])->name('users.index');
    Route::patch('/pengguna/{user:id}', [UserManagementController::class, 'update'])->name('users.update');

    // Kategori.
    Route::get('/kategori', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/kategori', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/kategori/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/kategori/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Jejak audit. Hanya-baca, jadi tidak ada POST/PUT/DELETE di sini.
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
});