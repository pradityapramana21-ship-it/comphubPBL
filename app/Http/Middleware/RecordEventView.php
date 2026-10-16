<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Event;
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
 *
 * Catatan penting soal nama parameter: rute publik memakai `/kegiatan/{event}`,
 * tempat "{event}" adalah NAMA parameternya dan slug dipakai lewat
 * getRouteKeyName(). Karena itu middleware ini mencari objek yang sudah
 * di-resolve di bawah nama "event" — bukan "slug". Mencari "slug" selalu
 * menghasilkan null, dan penghitung tidak pernah naik sama sekali.
 */
class RecordEventView
{
    public function handle(Request $request, Closure $next, string $parameter = 'event'): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 200 || ! $request->isMethod('GET')) {
            return $response;
        }

        $event = $request->route($parameter);

        // Hanya model Event yang dihitung. Kalau rute ini dipakai ulang
        // untuk model lain, penghitungnya tidak boleh ikut naik.
        if (! $event instanceof Event || ! $event->isPublished()) {
            return $response;
        }

        // Satu IP menambah maksimal satu hit per 30 detik per kegiatan.
        // Kunci memakai id, bukan slug, supaya kegiatan yang diganti
        // judulnya tidak mewarisi kuota tayangan yang sudah terpakai.
        $key = 'event-view:'.$request->ip().':'.$event->id;

        if (RateLimiter::attempt($key, 1, static fn (): bool => true, 30)) {
            $event->registerView();
        }

        return $response;
    }
}