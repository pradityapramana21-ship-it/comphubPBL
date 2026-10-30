<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\ValidationException;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Repositories\Pdo\PdoUserRepository;

function authReset(): void
{
    signOut();
    $_COOKIE = [];

    foreach (glob(dirname(__DIR__, 2) . '/storage/cache/rate-limits/*.json') ?: [] as $file) {
        unlink($file);
    }

    $mail = (string) App\Core\Config::get('app.mail.log_path');

    if (is_file($mail)) {
        unlink($mail);
    }
}

function authUser(string $email, string $password = 'secret123', UserRole $role = UserRole::User): array
{
    return (new PdoUserRepository(db()))->create(
        'Pengguna Uji',
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $role,
    );
}

test('auth registers, logs in, and never accepts a role from input', function (): void {
    authReset();
    $users = new PdoUserRepository(db());
    $email = 'daftar-' . uniqid() . '@example.test';

    $response = request('POST', '/daftar', [
        'name' => 'Pendaftar',
        'email' => $email,
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
        'website' => '',
    ]);

    assertSame(302, $response->status());
    $user = $users->findByEmail($email);
    assertTrue($user !== null);
    assertSame(UserRole::User->value, (string) $user['role']);
    assertSame((int) $user['id'], app()->auth()->id());

    signOut();
    $login = request('POST', '/masuk', [
        'email' => $email,
        'password' => 'secret123',
        'website' => '',
    ]);
    assertSame(302, $login->status());
    assertSame((int) $user['id'], app()->auth()->id());
});

test('auth rejects duplicate email and suspended accounts with the same login message', function (): void {
    authReset();
    $users = new PdoUserRepository(db());
    $existing = authUser('duplikat-' . uniqid() . '@example.test');

    assertThrows(ValidationException::class, fn () => request('POST', '/daftar', [
        'name' => 'Duplikat',
        'email' => $existing['email'],
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'website' => '',
    ]));

    $suspended = authUser('suspensi-' . uniqid() . '@example.test');
    $users->updateStatus((int) $suspended['id'], AccountStatus::Suspended, 'Uji');

    $failure = assertThrows(ValidationException::class, fn () => request('POST', '/masuk', [
        'email' => $suspended['email'],
        'password' => 'secret123',
        'website' => '',
    ]));
    assertSame(['Email atau kata sandi salah.'], $failure->errors()['email'] ?? []);
    assertFalse(app()->auth()->check());
});

test('auth rejects cross-site form posts without a valid token', function (): void {
    authReset();

    $exception = assertThrows(HttpException::class, fn () => request('POST', '/masuk', [
        '_token' => 'invalid',
        'email' => 'x@example.test',
        'password' => 'secret123',
        'website' => '',
    ]));

    assertSame(419, $exception->statusCode);
});

test('auth locks repeated login failures per email and address', function (): void {
    authReset();
    $user = authUser('kunci-' . uniqid() . '@example.test');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        assertThrows(ValidationException::class, fn () => request('POST', '/masuk', [
            'email' => $user['email'],
            'password' => 'salah-sekali',
            'website' => '',
        ]));
    }

    $exception = assertThrows(HttpException::class, fn () => request('POST', '/masuk', [
        'email' => $user['email'],
        'password' => 'secret123',
        'website' => '',
    ]));
    assertSame(429, $exception->statusCode);
    assertFalse(app()->auth()->check());
});

test('auth remember cookie restores the session and logout clears it', function (): void {
    authReset();
    $user = authUser('ingat-' . uniqid() . '@example.test');

    request('POST', '/masuk', [
        'email' => $user['email'],
        'password' => 'secret123',
        'remember' => '1',
        'website' => '',
    ]);
    assertSame((int) $user['id'], app()->auth()->id());

    signOut();
    assertFalse(app()->auth()->check());
    app()->auth()->bootFromRememberCookie();
    assertSame((int) $user['id'], app()->auth()->id());

    $logout = request('POST', '/keluar', ['website' => '']);
    assertSame(302, $logout->status());
    assertFalse(app()->auth()->check());
    assertSame(null, (new PdoUserRepository(db()))->findById((int) $user['id'])['remember_token'] ?? null);

    app()->auth()->bootFromRememberCookie();
    assertFalse(app()->auth()->check());
});

test('auth preserves safe next paths with query strings', function (): void {
    authReset();
    $user = authUser('arah-' . uniqid() . '@example.test');

    $response = request('POST', '/masuk', [
        'email' => $user['email'],
        'password' => 'secret123',
        'next' => '/kegiatan?q=lomba&status=open',
        'website' => '',
    ]);

    assertSame(302, $response->status());
    assertContains('/kegiatan?q=lomba&status=open', (string) $response->header('Location'));
});

test('auth rejects hostile next targets after login', function (): void {
    authReset();
    $user = authUser('arah-aman-' . uniqid() . '@example.test');
    $targets = [
        '//evil.example/path',
        '/%2f%2fevil.example/path',
        '/\\evil.example/path',
        'https://evil.example/path',
        "/kegiatan\r\nLocation: https://evil.example",
    ];

    foreach ($targets as $target) {
        $response = request('POST', '/masuk', [
            'email' => $user['email'],
            'password' => 'secret123',
            'next' => $target,
            'website' => '',
        ]);

        $location = (string) $response->header('Location');
        assertSame(302, $response->status());
        assertNotContains('evil.example', $location);
        assertContains('/dasbor', $location);
        signOut();
    }
});

test('auth routes admins home and refuses provider accounts', function (): void {
    authReset();
    $admin = authUser('admin-arah-' . uniqid() . '@example.test', role: UserRole::Admin);
    $adminLogin = request('POST', '/masuk', [
        'email' => $admin['email'],
        'password' => 'secret123',
        'website' => '',
    ]);
    assertContains('/admin', (string) $adminLogin->header('Location'));

    $guestPage = request('GET', '/masuk');
    assertSame(302, $guestPage->status());
    assertContains('/admin', (string) $guestPage->header('Location'));

    authReset();
    $provider = authUser('provider-tolak-' . uniqid() . '@example.test', role: UserRole::Provider);
    $failure = assertThrows(ValidationException::class, fn () => request('POST', '/masuk', [
        'email' => $provider['email'],
        'password' => 'secret123',
        'website' => '',
    ]));
    assertSame(['Email atau kata sandi salah.'], $failure->errors()['email'] ?? []);
    assertFalse(app()->auth()->check());
});

test('auth middleware keeps the full requested path in next', function (): void {
    signOut();
    $_COOKIE = [];

    $saved = request('GET', '/tersimpan?page=2');
    assertSame(302, $saved->status());
    assertContains('/masuk?next=%2Ftersimpan%3Fpage%3D2', (string) $saved->header('Location'));

    $admin = request('GET', '/admin?tab=queue');
    assertSame(302, $admin->status());
    assertContains('/masuk?next=%2Fadmin%3Ftab%3Dqueue', (string) $admin->header('Location'));
});

test('auth password reset issues a one-hour token and replaces the password', function (): void {
    authReset();
    $user = authUser('reset-' . uniqid() . '@example.test');

    $response = request('POST', '/lupa-sandi', ['email' => $user['email'], 'website' => '']);
    assertSame(302, $response->status());

    $mail = (string) file_get_contents((string) App\Core\Config::get('app.mail.log_path'));
    assertSame(1, preg_match('#/atur-sandi/([a-f0-9]{64})\?email=#', $mail, $matches));

    $page = request('GET', '/atur-sandi/' . $matches[1] . '?email=' . rawurlencode((string) $user['email']));
    assertSame(200, $page->status());

    $reset = request('POST', '/atur-sandi', [
        'token' => $matches[1],
        'email' => $user['email'],
        'password' => 'baru-sekali',
        'password_confirmation' => 'baru-sekali',
        'website' => '',
    ]);
    assertSame(302, $reset->status());

    authReset();
    assertThrows(ValidationException::class, fn () => request('POST', '/masuk', [
        'email' => $user['email'],
        'password' => 'secret123',
        'website' => '',
    ]));
    authReset();
    request('POST', '/masuk', [
        'email' => $user['email'],
        'password' => 'baru-sekali',
        'website' => '',
    ]);
    assertSame((int) $user['id'], app()->auth()->id());
});
