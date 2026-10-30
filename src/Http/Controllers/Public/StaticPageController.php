<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;

final class StaticPageController extends Controller
{
    public function __construct(View $view)
    {
        parent::__construct($view);
    }

    public function about(): Response
    {
        return $this->page('pages/about', 'Tentang', 'Tentang CompHub, sumber data, dan cara kurasi.');
    }

    public function moderation(): Response
    {
        return $this->page('pages/moderation', 'Kebijakan Kurasi', 'Sumber data, pemeriksaan tanggal, dan penandaan tenggat yang belum pasti.');
    }

    public function privacy(): Response
    {
        return $this->page('pages/privacy', 'Kebijakan Privasi', 'Data yang disimpan CompHub, kegunaannya, dan cara meminta penghapusan.');
    }

    public function providerGuide(): Response
    {
        return $this->page('pages/provider-guide', 'Panduan Penyelenggara', 'Cara kegiatan masuk ke katalog CompHub.');
    }

    public function contact(): Response
    {
        return $this->page('pages/contact', 'Kontak', 'Cara mengoreksi kegiatan dan menghubungi pengelola.');
    }

    private function page(string $template, string $title, string $description): Response
    {
        return $this->renderPage($template, [
            'title' => $title,
            'description' => $description,
        ]);
    }
}
