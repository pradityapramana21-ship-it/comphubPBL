<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Event;
use App\Models\EventReport;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Policies\EventReportPolicy;
use App\Policies\ProviderProfilePolicy;
use App\Policies\UserPolicy;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Repositories\Eloquent\EloquentBookmarkRepository;
use App\Repositories\Eloquent\EloquentCategoryRepository;
use App\Repositories\Eloquent\EloquentEventRepository;
use App\Repositories\Eloquent\EloquentModerationLogRepository;
use App\Repositories\Eloquent\EloquentProviderProfileRepository;
use App\Repositories\Eloquent\EloquentReportRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Titik pengikatan antarmuka ke implementasi.
 *
 * Aplikasi ini hanya mengenal interface. Itulah yang membuat
 * "controller, service, dan command melihat aturan yang sama" menjadi
 * struktural, bukan sekadar melanggar konvensi (Panduan §4.1.B).
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const REPOSITORIES = [
        UserRepository::class => EloquentUserRepository::class,
        EventRepository::class => EloquentEventRepository::class,
        ProviderProfileRepository::class => EloquentProviderProfileRepository::class,
        BookmarkRepository::class => EloquentBookmarkRepository::class,
        CategoryRepository::class => EloquentCategoryRepository::class,
        ModerationLogRepository::class => EloquentModerationLogRepository::class,
        ReportRepository::class => EloquentReportRepository::class,
        AuditLogRepository::class => EloquentAuditLogRepository::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORIES as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        $this->registerModerationRules();
    }

    /**
     * Daftar aturan moderasi yang dijalankan.
     *
     * Satu daftar, satu tempat. Menambah aturan berarti menambah satu baris
     * di sini — kode aturan lain tidak perlu disentuh, dan urutannya bisa
     * dibaca admin.
     *
     * @var list<class-string<ModerationRule>>
     */
    private const MODERATION_RULES = [
        \App\Services\Moderation\Rules\DateSanityRule::class,
        \App\Services\Moderation\Rules\ContentQualityRule::class,
        \App\Services\Moderation\Rules\SuspiciousUrlRule::class,
        \App\Services\Moderation\Rules\ContactLeakRule::class,
        \App\Services\Moderation\Rules\DuplicateEventRule::class,
        \App\Services\Moderation\Rules\RateLimitRule::class,
        \App\Services\Moderation\Rules\BlockedKeywordRule::class,
    ];

    private function registerModerationRules(): void
    {
        foreach (self::MODERATION_RULES as $rule) {
            $this->app->singleton($rule);
        }

        $this->app->bind(
            \App\Services\ModerationService::class,
            fn ($app): \App\Services\ModerationService => new \App\Services\ModerationService(
                $app->make(ModerationLogRepository::class),
                $app->make(EventRepository::class),
                ...array_map(
                    static fn (string $rule): \App\Services\Moderation\ModerationRule => $app->make($rule),
                    self::MODERATION_RULES,
                ),
            ),
        );
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerRateLimiters();
        $this->registerGates();
    }

    /**
     * Peta kebijakan otorisasi.
     *
     * Semua policy dipetakan di sini supaya tidak ada model yang lolos
     * pemeriksaan secara tidak sengaja.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(ProviderProfile::class, ProviderProfilePolicy::class);
        Gate::policy(EventReport::class, EventReportPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Pembatas laju permintaan (Panduan §7.2).
     *
     * Nilai sengaja longgar untuk pemakaian normal dan ketat untuk login,
     * supaya orang bisa salah ketik password dua kali, tapi tidak bisa
     * menebak-nebak tanpa batas.
     */
    private function registerRateLimiters(): void
    {
        // Batas volume, bukan batas kegagalan. Penghitung kegagalan yang
        // ketat ada di LoginRequest dengan pesannya sendiri.
        //
        // Catatan penting: middleware menyusun kunci penghitungnya secara
        // internal, jadi halaman login tidak bisa mengosongkannya. Karena
        // itu batas di sini sengaja longgar — kalau ketat, orang yang
        // salah ketik tiga kali lalu berhasil masuk akan terkunci 15 menit.
        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinutes(15, $request->input('email') ? 30 : 10)
                ->by('login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            // Batas per alamat IP dibuat tinggi dengan sengaja: ribuan
            // mahasiswa sering berada di balik satu IP publik (NAT kampus),
            // jadi batas ketat akan mengunci seisi kampus sekaligus.
            Limit::perMinutes(60, 60)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request): Limit =>
            Limit::perMinutes(60, 5)->by('register:'.$request->ip()));

        RateLimiter::for('api', fn (Request $request): Limit =>
            Limit::perMinute(120)->by('api:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('event-submit', fn (Request $request): Limit =>
            Limit::perHour(10)->by('event-submit:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('bookmark', fn (Request $request): Limit =>
            Limit::perMinute(30)->by('bookmark:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('report', fn (Request $request): Limit =>
            Limit::perHour(10)->by('report:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('contact', fn (Request $request): Limit =>
            Limit::perHour(3)->by('contact:'.$request->ip()));

        // Pesan default Laravel dalam bahasa Inggris; diganti supaya
        // konsisten dengan seluruh antarmuka yang berbahasa Indonesia.
        RateLimiter::for('global', fn (Request $request): Limit =>
            Limit::perMinute(180)->by('global:'.($request->user()?->id ?? $request->ip())));
    }

    /**
     * Pintasan Gerbang yang sering dipakai.
     *
     * Hanya alias: pintasan yang sering dipanggil. Tidak ada keputusan
     * apa pun di sini — keputusan ada di policy masing-masing model.
     */
    private function registerGates(): void
    {
        Gate::define('moderate-events', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-audit-log', fn (User $user): bool => $user->isAdmin());
    }
}