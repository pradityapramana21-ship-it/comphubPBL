<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman statis: tentang, kebijakan moderasi, panduan penyelenggara.
 *
 * Isinya ditulis sebagai view, bukan dari database, supaya tidak bisa
 * diubah lewat panel admin tanpa jejak. Kalau suatu saat perlu
 * disunting lewat panel, pindahkan ke tabel dan catat di change-log
 * (Panduan §3.1).
 */
class StaticPageController extends Controller
{
    public function about(): View
    {
        return view('public.pages.about');
    }

    /**
     * Kebijakan moderasi.
     *
     * Halaman ini adalah penjelasan yang bisa dibaca penyedia, bukan
     * dokumentasi internal: memuat aturan yang benar-benar dipakai
     * config/moderation.php, lengkap dengan ambangnya.
     */
    public function moderationPolicy(): View
    {
        return view('public.pages.moderation', [
            'thresholds' => config('moderation.thresholds'),
            'weights' => config('moderation.weights'),
            'rateLimit' => config('moderation.rate_limit'),
            'quality' => config('moderation.content_quality'),
        ]);
    }

    /** Panduan bagi organisasi yang ingin mengiklankan kegiatan. */
    public function providerGuide(): View
    {
        return view('public.pages.provider-guide');
    }

    public function privacy(): View
    {
        return view('public.pages.privacy');
    }

    public function contact(): View
    {
        return view('public.pages.contact');
    }
}
