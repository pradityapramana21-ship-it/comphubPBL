<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use Illuminate\View\View;

/**
 * Beranda.
 *
 * Hanya menampilkan kegiatan yang published DAN pendaftarannya masih
 * dibuka. Kegiatan yang lewat tenggat tidak pernah muncul di sini, walau
 * statusnya masih approved — lapisan kedua di samping command penjadwal
 * (Panduan FR-03, TC-DATE-01).
 */
class HomeController extends Controller
{
    public function __invoke(EventRepository $events, ProviderProfileRepository $providers): View
    {
        $filter = CatalogFilter::fromQuery([])->withoutDeadlineFilter();

        $terdekat = $events->paginatePublicCatalog(
            CatalogFilter::fromQuery(['sort' => 'paling_dekat'])->withLimit(6),
        );

        $terbaru = $events->paginatePublicCatalog($filter->withLimit(6));

        return view('public.home', [
            'terdekat' => $terdekat,
            'terbaru' => $terbaru,
            'totalKegiatan' => app(EventRepository::class)->countAll(),
            'totalPenyedia' => $providers->countAll(),
            'statistikTipe' => $this->countsByCategory($events),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function countsByCategory(EventRepository $events): array
    {
        $counts = [];

        foreach ($events->countByStatus() as $status => $total) {
            $counts[$status] = $total;
        }

        return $counts;
    }
}
