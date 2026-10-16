<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Services\BookmarkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dasbor pengguna biasa.
 *
 * Halaman pertama setelah masuk. Isinya sengaja sedikit: tiga angka
 * dan daftar kegiatan yang mendekati tenggat. Dasbor yang penuh
 * grafik untuk pengguna yang datanya masih sedikit hanya menambah
 * pekerjaan tanpa manfaat.
 */
class UserDashboardController extends Controller
{
    public function __invoke(
        Request $request,
        BookmarkService $bookmarks,
        EventRepository $events,
    ): View {
        $user = $request->user();

        $tersimpan = $bookmarks->paginateForUser($user, perPage: 6);

        // Kegiatan yang tayang, punya tenggat dekat, dan belum disimpan.
        $disarankan = $events->paginatePublicCatalog(
            CatalogFilter::fromQuery(['sort' => 'paling_dekat'])->withLimit(4),
        )->getCollection()
            ->reject(fn ($event): bool => $bookmarks->isSaved($user, $event))
            ->values();

        return view('user.dashboard', [
            'user' => $user,
            'tersimpan' => $tersimpan,
            'totalTersimpan' => $tersimpan->total(),
            'disarankan' => $disarankan,
            'laporanSaya' => $user->reports()->latest()->limit(5)->get(),
        ]);
    }
}
