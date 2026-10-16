<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookmarkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Tersimpan".
 *
 * Perhatikan apa yang TIDAK ada di sini: parameter user_id. Daftar ini
 * selalu milik orang yang sedang masuk, tanpa kecuali (Panduan §6.5).
 *
 * Menyimpan dan membatalkan penyimpanan dilakukan EventCatalogController, lewat
 * POST /events/{event}/bookmark dan DELETE-nya — supaya keduanya memakai
 * bentuk yang sama dengan yang dipakai halaman detail kegiatan.
 */
class BookmarkController extends Controller
{
    public function index(Request $request, BookmarkService $bookmarks): View
    {
        return view('user.bookmarks', [
            'bookmarks' => $bookmarks->paginateForUser($request->user(), perPage: 12),
        ]);
    }
}