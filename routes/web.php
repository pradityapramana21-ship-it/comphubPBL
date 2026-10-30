<?php

declare(strict_types=1);

use App\Core\Router;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminEventController;
use App\Http\Controllers\Admin\AdminProviderController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\BookmarkController;
use App\Http\Controllers\Public\EventCatalogController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProviderDirectoryController;
use App\Http\Controllers\Public\StaticPageController;
use App\Http\Controllers\UserDashboardController;

/** @var Router $router */
$router = app()->router();

$router->get('/', [HomeController::class, '__invoke'])->name('home');

$router->get('/tentang', [StaticPageController::class, 'about'])->name('pages.about');
$router->get('/kebijakan-moderasi', [StaticPageController::class, 'moderation'])->name('pages.moderation');
$router->get('/kebijakan-privasi', [StaticPageController::class, 'privacy'])->name('pages.privacy');
$router->get('/panduan-penyelenggara', [StaticPageController::class, 'providerGuide'])->name('pages.providerGuide');
$router->get('/kontak', [StaticPageController::class, 'contact'])->name('pages.contact');

$router->get('/penyelenggara', [ProviderDirectoryController::class, 'index'])->name('providers.index');
$router->get('/penyelenggara/{slug}', [ProviderDirectoryController::class, 'show'])->name('providers.show');

$router->get('/kegiatan', [EventCatalogController::class, 'index'])->name('events.index');
$router->post('/kegiatan/{event}/bookmark', [BookmarkController::class, 'store'])
    ->name('events.bookmark.store')->middleware('auth', 'throttle:bookmark');
$router->delete('/kegiatan/{event}/bookmark', [BookmarkController::class, 'destroy'])
    ->name('events.bookmark.destroy')->middleware('auth');
$router->get('/kegiatan/{slug}', [EventCatalogController::class, 'show'])->name('events.show');

$router->get('/masuk', [AuthController::class, 'showLogin'])->name('auth.login')->middleware('guest');
$router->post('/masuk', [AuthController::class, 'login'])->name('auth.login.attempt')->middleware('guest');
$router->post('/keluar', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth');

$router->get('/daftar', [AuthController::class, 'showRegister'])->name('auth.register')->middleware('guest');
$router->post('/daftar', [AuthController::class, 'register'])->name('auth.register.store')->middleware('guest', 'throttle:register');

$router->get('/dasbor', [UserDashboardController::class, 'index'])->name('user.dashboard')->middleware('auth');
$router->get('/tersimpan', [UserDashboardController::class, 'saved'])->name('user.saved')->middleware('auth');
$router->get('/profil', [ProfileController::class, 'edit'])->name('user.profile')->middleware('auth');
$router->post('/profil', [ProfileController::class, 'update'])->name('user.profile.update')->middleware('auth');
$router->post('/profil/sandi', [ProfileController::class, 'updatePassword'])->name('user.profile.password')->middleware('auth');

$router->get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index')->middleware('auth');
$router->post('/notifikasi/tandai-dibaca', [NotificationController::class, 'markRead'])
    ->name('notifications.read')->middleware('auth');

$router->get('/lupa-sandi', [PasswordResetController::class, 'showRequest'])->name('auth.password.request');
$router->post('/lupa-sandi', [PasswordResetController::class, 'send'])->name('auth.password.email');
$router->get('/atur-sandi/{token}', [PasswordResetController::class, 'showReset'])->name('auth.password.reset');
$router->post('/atur-sandi', [PasswordResetController::class, 'update'])->name('auth.password.update');

$router->get('/admin', [AdminDashboardController::class, 'index'])->name('admin.index')->middleware('role:admin');
$router->get('/admin/pengguna', [AdminUserController::class, 'index'])->name('admin.users.index')->middleware('role:admin');
$router->get('/admin/pengguna/baru', [AdminUserController::class, 'create'])->name('admin.users.create')->middleware('role:admin');
$router->post('/admin/pengguna', [AdminUserController::class, 'store'])->name('admin.users.store')->middleware('role:admin');
$router->get('/admin/pengguna/{user:id}', [AdminUserController::class, 'edit'])->name('admin.users.edit')->middleware('role:admin');
$router->post('/admin/pengguna/{user:id}', [AdminUserController::class, 'update'])->name('admin.users.update')->middleware('role:admin');
$router->post('/admin/pengguna/{user:id}/hapus', [AdminUserController::class, 'destroy'])->name('admin.users.destroy')->middleware('role:admin');

$router->get('/admin/kegiatan', [AdminEventController::class, 'index'])->name('admin.events.index')->middleware('role:admin');
$router->get('/admin/kegiatan/baru', [AdminEventController::class, 'create'])->name('admin.events.create')->middleware('role:admin');
$router->post('/admin/kegiatan', [AdminEventController::class, 'store'])->name('admin.events.store')->middleware('role:admin');
$router->get('/admin/kegiatan/{event:id}', [AdminEventController::class, 'edit'])->name('admin.events.edit')->middleware('role:admin');
$router->post('/admin/kegiatan/{event:id}', [AdminEventController::class, 'update'])->name('admin.events.update')->middleware('role:admin');
$router->post('/admin/kegiatan/{event:id}/hapus', [AdminEventController::class, 'destroy'])->name('admin.events.destroy')->middleware('role:admin');

$router->get('/admin/penyedia', [AdminProviderController::class, 'index'])->name('admin.providers.index')->middleware('role:admin');
$router->get('/admin/penyedia/{provider:id}', [AdminProviderController::class, 'edit'])->name('admin.providers.edit')->middleware('role:admin');
$router->post('/admin/penyedia/{provider:id}', [AdminProviderController::class, 'update'])->name('admin.providers.update')->middleware('role:admin');

$router->get('/admin/laporan', [AdminReportController::class, 'index'])->name('admin.reports.index')->middleware('role:admin');
$router->post('/admin/laporan/{report:id}', [AdminReportController::class, 'update'])->name('admin.reports.update')->middleware('role:admin');

$router->get('/admin/audit', [AdminAuditController::class, 'index'])->name('admin.audit.index')->middleware('role:admin');
