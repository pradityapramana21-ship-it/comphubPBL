<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat bahwa sebuah kegiatan dilihat.
 *
 * Angka tayangan tidak boleh dipercaya begitu saja: kalau penghitungnya
 * sederhana, satu orang yang memuat ulang halaman sepuluh kali akan
 * tercatat sepuluh tayangan. Rate limiter per-IP menjaga agar penghitung
 * tetap mendekati jumlah orang, bukan jumlah permintaan.
 *
 * Penghitung disimpan di database lewat repository, bukan di memori,
 * supaya tetap konsisten di antara beberapa proses PHP.
 */
class RecordEventView
{
    public function handle(Request $request, Closure $next, string $parameter = 'slug'): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 200 || ! $request->isMethod('GET')) {
            return $response;
        }

        $slug = $request->route($parameter);

        if (! is_string($slug) || $slug === '') {
            return $response;
        }

        $key = 'event-view:'.$request->ip();

        // Satu IP menambah maksimal satu hit per 30 detik per kegiatan.
        if (RateLimiter::attempt($key.':'.$slug, 1, static fn (): bool => true, 30)) {
            $event = $request->route('event');

            // Hanya kegiatan yang benar-benar tayang yang dihitung. Kalau
            // tidak, satu orang bisa menaikkan angka dengan membuka URL
            // draf milik orang lain berulang kali.
            if ($event instanceof \App\Models\Event && $event->isPublished()) {
                $event->registerView();
            }
        }

        return $response;
    }
}
