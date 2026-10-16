{{--
    Layout utama: navbar + konten + footer.

    Prinsip yang dipegang di sini:
      * Tidak ada @vite. Aset dimuat lewat asset() supaya nol build step.
      * CSRF token dan locale diteruskan lewat <meta>, dibaca app.js.
      * Setiap halaman punya satu <h1> yang jelas — penting untuk pembaca
        layar, bukan cuma untuk mesin pencari.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? 'Informasi Kegiatan Kampus') — {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Temukan informasi lomba, webinar, dan seminar dari penyelenggara mahasiswa yang sudah terverifikasi.')">

    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">

    {{-- Bootstrap 5 lewat CDN. Alasannya proyek ini tanpa build step:
         anggota cukup php artisan serve tanpa npm install. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    {{-- Token dimuat SEBELUM komponen, karena komponen menulis ulang
         variabel Bootstrap dengan nilai dari token. --}}
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<a class="skip-link" href="#konten-utama">Lompat ke konten utama</a>

@include('partials.navbar')

<main id="konten-utama" class="flex-grow-1">
    <div class="container py-4">
        @include('partials.flash')

        @yield('content')
    </div>
</main>

@include('partials.footer')

<div class="toast-stack" data-toast-stack>
    @include('partials.toast')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script type="module" src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
