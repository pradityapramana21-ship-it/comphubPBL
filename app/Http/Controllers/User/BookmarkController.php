<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookmarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Tersimpan".
 *
 * Perhatikan apa yang TIDAK ada di sini: parameter user_id. Daftar ini
 * selalu milik orang yang sedang masuk, tanpa kecuali (Panduan §6.5).
 */
class BookmarkController extends Controller
{
    public function index(Request $request, BookmarkService $bookmarks): View
    {
        return view('user.bookmarks', [
            'bookmarks' => $bookmarks->paginateForUser($request->user(), perPage: 12),
            'savedMap' => [],
        ]);
    }

    /**
     * Toggle: dipakai tombol di halaman detail kegiatan.
     *
     * POST, bukan GET. GET tidak boleh mengubah state — kalau tombol ini
     * memakai GET, siapa pun bisa memicu penyimpanan diam-diam lewat
     * <img src="..."> dan prefetch browser (Panduan §8, OWASP CSRF).
     */
    public function toggle(Request $request, \App\Models\Event $event, BookmarkService $bookmarks): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $this->authorize('bookmark', $event);

        $saved = $bookmarks->toggle($user, $event);

        return back()->with(
            'success',
            $saved ? 'Disimpan ke daftar Tersimpan.' : 'Dihapus dari daftar Tersimpan.',
        );
    }
}
