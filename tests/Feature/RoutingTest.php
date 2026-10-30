<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

test('router matches static and parameterised paths', function (): void {
    $router = new Router(app()->container());
    $router->get('/kegiatan', static fn (): Response => Response::html('index'))->name('events.index');
    $router->get('/kegiatan/{event}', static function (Request $request): Response {
        return Response::html('show:' . $request->attribute('route')['event']);
    })->name('events.show');

    assertSame('index', $router->dispatch(Request::create('GET', '/kegiatan'))->body());
    assertSame('show:lomba-2026', $router->dispatch(Request::create('GET', '/kegiatan/lomba-2026'))->body());
    assertSame('/kegiatan/lomba-2026', $router->url('events.show', ['event' => 'lomba-2026']));
});

test('router returns 404 and 405 with correct status', function (): void {
    $router = new Router(app()->container());
    $router->post('/masuk', static fn (): Response => Response::html('ok'));

    assertThrows(HttpException::class, static fn () => $router->dispatch(Request::create('GET', '/missing')));
    $exception = assertThrows(HttpException::class, static fn () => $router->dispatch(Request::create('GET', '/masuk')));
    assertSame(405, $exception->statusCode);
});

test('provider self-registration is not advertised while unavailable', function (): void {
    signOut();
    $_COOKIE = [];
    $guide = request('GET', '/panduan-penyelenggara')->body();

    assertNotContains('/daftar/penyelenggara', $guide);
    $missing = assertThrows(HttpException::class, static fn () => request('GET', '/daftar/penyelenggara'));
    assertSame(404, $missing->statusCode);
});

test('router binds numeric id parameters only', function (): void {
    $router = new Router(app()->container());
    $router->get('/kegiatan/{event:id}', static fn (): Response => Response::html('ok'));

    assertSame('ok', $router->dispatch(Request::create('GET', '/kegiatan/12'))->body());
    assertThrows(HttpException::class, static fn () => $router->dispatch(Request::create('GET', '/kegiatan/abc')));
});

test('catalog type routes render the Luarkampus shell', function (): void {
    signOut();
    $_COOKIE = [];

    foreach (['/lomba' => 'Lomba', '/webinar' => 'Webinar', '/seminar' => 'Seminar'] as $path => $label) {
        $response = request('GET', $path);
        assertSame(200, $response->status(), $path);
        $html = $response->body();
        assertContains('site-header', $html);
        assertContains('filter-sidebar', $html);
        assertContains('month-tabs', $html);
        assertContains('event-grid', $html);
        assertContains($label, $html);
        assertContains('Ajukan Kegiatan', $html);
        assertNotContains('class="glass', $html);
    }
});
