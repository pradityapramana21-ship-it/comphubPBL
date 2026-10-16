<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\DataTransferObjects\CatalogFilter;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\EventRepository;
use App\Repositories\Contracts\ProviderProfileRepository;
use Illuminate\Http\Request;

class ProviderDirectoryController extends Controller
{
    public function index(Request $request, ProviderProfileRepository $providers): \Illuminate\View\View
    {
        return view('public.providers.index', [
            'providers' => $providers->paginatePublicDirectory(
                keyword: $request->query('q'),
                perPage: 12,
            ),
            'keyword' => trim((string) $request->query('q', '')),
        ]);
    }

    /**
     * Profil publik Sebuah penyelenggara.
     *
     * Hanya profil terverifikasi yang bisa dibuka. Daftar di atas memakai
     * `having('events_count', '>', 0)`, jadi organisasi yang belum pernah
     * punya kegiatan tayang pun tidak muncul sebagai halaman kosong.
     */
    public function show(
        string $slug,
        ProviderProfileRepository $providers,
        EventRepository $events,
    ): \Illuminate\View\View {
        $provider = $providers->findVerifiedBySlug($slug);

        abort_if($provider === null, 404, 'Penyelenggara tidak ditemukan.');

        $paginator = $events->paginatePublicCatalog(
            CatalogFilter::fromQuery([])->forProviderId($provider->id)->withLimit(9),
        );

        return view('public.providers.show', [
            'provider' => $provider,
            'events' => $paginator,
            // Jumlah penyimpanan diambil dari kolom cache di tabel events,
            // bukan dihitung ulang dengan COUNT pada tabel bookmarks.
            'totalTersimpan' => (int) $paginator->getCollection()->sum('bookmark_count'),
        ]);
    }
}
