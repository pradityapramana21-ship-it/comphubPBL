<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Http\Middleware\MiddlewareRegistry;
use App\Repositories\Contracts\AuditLogRepository;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ModerationLogRepository;
use App\Repositories\Contracts\PasswordResetRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use App\Repositories\Contracts\ReportRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Pdo\PdoAuditLogRepository;
use App\Repositories\Pdo\PdoBookmarkRepository;
use App\Repositories\Pdo\PdoCategoryRepository;
use App\Repositories\Pdo\PdoEventRepository;
use App\Repositories\Pdo\PdoModerationLogRepository;
use App\Repositories\Pdo\PdoPasswordResetRepository;
use App\Repositories\Pdo\PdoProviderProfileRepository;
use App\Repositories\Pdo\PdoReportRepository;
use App\Repositories\Pdo\PdoUserRepository;
use App\Services\CurationProfileService;
use App\Services\EventSubmissionService;
use App\Services\RevisionTokenService;
use App\Services\UploadService;
use App\Support\FileCache;
use App\Support\LogMailer;
use App\Support\Mailer;
use App\Support\RateLimiter;

$root = dirname(__DIR__);
$testing = getenv('APP_ENV') === 'testing';

require $root . '/src/Core/Autoloader.php';
Autoloader::register($root . '/src');

$envFile = $testing ? $root . '/.env.testing' : $root . '/.env';

if (! is_file($envFile) && $testing && is_file($root . '/.env.testing.example')) {
    $envFile = $root . '/.env.testing.example';
}

Env::load($envFile);

if (getenv('APP_ENV') === 'testing') {
    Env::set('APP_ENV', 'testing');
}

Config::load($root . '/config');

require $root . '/bootstrap/helpers.php';

$container = new Container();
$container->instance(Container::class, $container);
$container->singleton(Database::class, static fn (): Database => Database::connectFromConfig());
$container->singleton(Session::class, static fn (): Session => new Session());
$container->singleton(Auth::class, static fn (Container $c): Auth => new Auth($c->make(Session::class), $c));
$container->singleton(Csrf::class, static fn (Container $c): Csrf => new Csrf($c->make(Session::class)));
$container->singleton(RateLimiter::class, static fn (): RateLimiter => new RateLimiter($root . '/storage/cache/rate-limits'));
$container->singleton(FileCache::class, static fn (): FileCache => new FileCache($root . '/storage/cache/data'));
$container->singleton(Mailer::class, static fn (): Mailer => new LogMailer((string) Config::get('app.mail.log_path')));
$container->singleton(View::class, static fn (): View => new View($root . '/resources/views'));
$container->singleton(Router::class, static fn (Container $c): Router => new Router($c));
$container->singleton(MiddlewareRegistry::class, static fn (Container $c): MiddlewareRegistry => new MiddlewareRegistry($c));

$container->singleton(UserRepository::class, static fn (Container $c): UserRepository => new PdoUserRepository($c->make(Database::class)));
$container->singleton(CategoryRepository::class, static fn (Container $c): CategoryRepository => new PdoCategoryRepository($c->make(Database::class)));
$container->singleton(ProviderProfileRepository::class, static fn (Container $c): ProviderProfileRepository => new PdoProviderProfileRepository($c->make(Database::class)));
$container->singleton(EventRepository::class, static fn (Container $c): EventRepository => new PdoEventRepository($c->make(Database::class)));
$container->singleton(BookmarkRepository::class, static fn (Container $c): BookmarkRepository => new PdoBookmarkRepository($c->make(Database::class)));
$container->singleton(ReportRepository::class, static fn (Container $c): ReportRepository => new PdoReportRepository($c->make(Database::class)));
$container->singleton(ModerationLogRepository::class, static fn (Container $c): ModerationLogRepository => new PdoModerationLogRepository($c->make(Database::class)));
$container->singleton(AuditLogRepository::class, static fn (Container $c): AuditLogRepository => new PdoAuditLogRepository($c->make(Database::class)));
$container->singleton(PasswordResetRepository::class, static fn (Container $c): PasswordResetRepository => new PdoPasswordResetRepository($c->make(Database::class)));
$container->singleton(CurationProfileService::class, static fn (Container $c): CurationProfileService => new CurationProfileService($c->make(Database::class)));
$container->singleton(UploadService::class, static fn (): UploadService => new UploadService($root . '/storage/uploads'));
$container->singleton(RevisionTokenService::class, static fn (Container $c): RevisionTokenService => new RevisionTokenService($c->make(EventRepository::class)));
$container->singleton(EventSubmissionService::class, static fn (Container $c): EventSubmissionService => new EventSubmissionService(
    $c->make(EventRepository::class),
    $c->make(CurationProfileService::class),
    $c->make(UploadService::class),
    new \App\Support\SlugGenerator($c->make(Database::class)),
));

$app = new App($container);
$container->instance(App::class, $app);

$registry = $container->make(MiddlewareRegistry::class);
$container->make(Router::class)->setMiddlewareResolver(
    static fn (string $name) => $registry->resolve($name),
);

require $root . '/routes/web.php';

return $app;
