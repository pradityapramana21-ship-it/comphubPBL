<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'CompHub') ?? 'CompHub',
    'env' => Env::get('APP_ENV', 'production') ?? 'production',
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim(Env::get('APP_URL', 'http://localhost:8080') ?? 'http://localhost:8080', '/'),
    'key' => Env::get('APP_KEY', '') ?? '',
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta') ?? 'Asia/Jakarta',
    'session' => [
        'name' => Env::get('SESSION_COOKIE', 'comphub_session') ?? 'comphub_session',
        'lifetime' => Env::int('SESSION_LIFETIME', 120),
        'path' => '/',
    ],
    'mail' => [
        'driver' => Env::get('MAIL_MAILER', 'log') ?? 'log',
        'from' => Env::get('MAIL_FROM', 'no-reply@localhost') ?? 'no-reply@localhost',
        'log_path' => Env::get('MAIL_LOG_PATH', dirname(__DIR__) . '/storage/logs/mail.log') ?? dirname(__DIR__) . '/storage/logs/mail.log',
    ],
    'database' => [
        'host' => Env::get('DB_HOST', '127.0.0.1') ?? '127.0.0.1',
        'port' => Env::int('DB_PORT', 5432),
        'database' => Env::get('DB_DATABASE', 'lomba_kampus_native') ?? 'lomba_kampus_native',
        'username' => Env::get('DB_USERNAME', 'postgres') ?? 'postgres',
        'password' => Env::get('DB_PASSWORD', '') ?? '',
    ],
];
