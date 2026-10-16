<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Repositories\Contracts\BookmarkRepository;
use App\Repositories\Contracts\EventRepository;
use App\Services\BookmarkService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/**
 * Katalog kegiatan: daftar dan detail.
 *
 * Tidak ada satupun aksi yang mengubah data di sini kecuali bookmark,
 * dan itu pun hanya untuk pengguna yang sudah masuk. Semua penulisan
 * lainnya milik penyedia lewat dashboard-nya.
 */
class EventCatalogController extends Controller
{
    public function index(Request $request, EventRepository $events): \Illuminate\View\View
    {
        $filter = CatalogFilter::fromQuery($request->query());

        $paginator = $events->paginatePublicCatalog($filter);

        return view('public.events.index', [
            'events' => $paginator,
            'filter' => $filter,
            'categories' => $this->categories(),
            'sortOptions' => Event::sortableOptions(),
        ]);
    }

    /**
     * Detail kegiatan.
     *
     * Hanya kegiatan berstatus approved yang bisa dibuka lewat URL ini.
     * Menebak ID atau slug milik kegiatan milik orang lain tidak
     * memberi apa-apa — query-nya sendiri sudah dibatasi status
     * (Panduan §6.5, TC-USER-02).
     */
    public function show(Request $request, string $slug, EventRepository $events, BookmarkService $bookmarks): \Illuminate\View\View
    {
        $event = $events->findPublishedBySlug($slug);

        // 404, bukan 403: bagi pengunjung halaman ini memang tidak ada.
        abort_if($event === null, 404, 'Kegiatan tidak ditemukan atau belum ditayangkan.');

        $user = $request->user();

        return view('public.events.show', [
            'event' => $event,
            'saved' => $user !== null && $bookmarks->isSaved($user, $event),
            'similar' => $events
                ->paginatePublicCatalog(
                    CatalogFilter::fromQuery(['category' => $event->category_id])
                        ->withoutDeadlineFilter()
                        ->withLimit(4),
                )
                ->getCollection()
                ->reject(fn (Event $other): bool => $other->id === $event->id)
                ->values(),
            'openReports' => $user?->isAdmin() === true ? $event->openReports()->count() : 0,
        ]);
    }

    /**
     * Simpan kegiatan.
     *
     * ID pengguna SELALU dari sesi. Field `user_id` yang dikirim form
     * diabaikan sepenuhnya, sehingga mengubahnya lewat devtools tidak
     * mengubah siapa yang menyimpan (Panduan §6.5).
     */
    public function store(Request $request, Event $event, BookmarkService $bookmarks): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $this->authorize('bookmark', $event);

        $saved = $bookmarks->add($user, $event);

        return back()->with(
            $saved ? 'success' : 'error',
            $saved ? 'Kegiatan disimpan ke daftar Tersimpan.' : 'Kegiatan ini sudah ada di daftar Tersimpan.',
        );
    }

    /** Batalkan penyimpanan. */
    public function destroy(Request $request, Event $event, BookmarkService $bookmarks): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $removed = $bookmarks->remove($user, $event);

        return back()->with(
            'success',
            $removed ? 'Kegiatan dihapus dari daftar Tersimpan.' : 'Kegiatan itu memang tidak ada di daftar Tersimpan.',
        );
    }
}
