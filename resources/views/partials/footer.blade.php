<footer class="border-top bg-white py-4 mt-5 no-print" style="border-color: var(--color-border) !important;">
    <div class="container">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <img src="{{ asset('images/logo.svg') }}" alt="" width="26" height="26">
                    <strong>{{ config('app.name') }}</strong>
                </div>
                <p class="small text-muted-soft mb-0" style="max-width: 36ch;">
                    Katalog kegiatan mahasiswa dari penyelenggara yang sudah diverifikasi.
                </p>
            </div>

            <div class="col-6 col-md-2">
                <p class="sidebar__label mb-2">Jelajahi</p>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><a href="{{ route('events.index') }}">Semua kegiatan</a></li>
                    <li class="mb-1"><a href="{{ route('providers.index') }}">Penyelenggara</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-2">
                <p class="sidebar__label mb-2">Informasi</p>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><a href="{{ route('pages.about') }}">Tentang</a></li>
                    <li class="mb-1"><a href="{{ route('pages.moderation') }}">Kebijakan moderasi</a></li>
                    <li class="mb-1"><a href="{{ route('pages.providerGuide') }}">Panduan penyelenggara</a></li>
                    <li class="mb-1"><a href="{{ route('pages.privacy') }}">Kebijakan privasi</a></li>
                </ul>
            </div>

            <div class="col-md-4">
                <p class="sidebar__label mb-2">Ikuti</p>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><a href="{{ route('pages.contact') }}">Hubungi kami</a></li>
                </ul>
                <p class="small text-muted-soft mt-3 mb-0">
                    Proyek-Based Learning &mdash; tugas mata kuliah.
                </p>
            </div>
        </div>
    </div>
</footer>
