<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;

putenv('APP_ENV=testing');
putenv('APP_DEBUG=true');
putenv('DB_DATABASE=lomba_kampus_native_test');
putenv('APP_KEY=testing-key-change-me');
putenv('MAIL_LOG_PATH=' . dirname(__DIR__) . '/storage/logs/mail-test.log');

$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var App $app */
$GLOBALS['app'] = $app;

$GLOBALS['__tests'] = [];
$GLOBALS['__failures'] = [];

function test(string $name, callable $callback): void
{
    $GLOBALS['__tests'][] = [$name, $callback];
}

function assertTrue(bool $condition, string $message = 'Expected true'): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message = 'Expected false'): void
{
    assertTrue(! $condition, $message);
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true),
        );
    }
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    if (! str_contains($haystack, $needle)) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '') . 'missing ' . var_export($needle, true),
        );
    }
}

function assertNotContains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '') . 'unexpected ' . var_export($needle, true),
        );
    }
}

function assertThrows(string $class, callable $callback): Throwable
{
    try {
        $callback();
    } catch (Throwable $exception) {
        if (! $exception instanceof $class) {
            throw new RuntimeException('Expected ' . $class . ', got ' . $exception::class);
        }

        return $exception;
    }

    throw new RuntimeException('Expected exception ' . $class . ' was not thrown');
}

function db(): Database
{
    static $database = null;

    return $database ??= Database::connectFromConfig();
}

function signIn(int $userId): void
{
    app()->session()->start();
    app()->session()->put('auth_user_id', $userId);
    app()->auth()->refresh();
}

function signOut(): void
{
    app()->session()->forget('auth_user_id');
    app()->auth()->refresh();
}

/**
 * @param array<string, mixed> $input
 * @param array<string, string> $headers
 */
function request(string $method, string $path, array $input = [], array $headers = []): Response
{
    if (! in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
        $token = app()->csrf()->token();
        $input['_token'] = $input['_token'] ?? $token;
    }

    return app()->handle(Request::create($method, $path, $input, $headers));
}

function rawRequest(string $method, string $path, array $input = [], array $headers = []): Response
{
    return app()->invoke(Request::create($method, $path, $input, $headers));
}
