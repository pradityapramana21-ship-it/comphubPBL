{{--
    Layout untuk halaman masuk, daftar, dan atur ulang kata sandi.

    Tanpa navbar: halaman ini punya satu tugas, dan navigasi lengkap hanya
    mengalihkan perhatian darinya.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') — {{ config('app.name') }}</title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="d-flex flex-column min-vh-100">
<main class="flex-grow-1 d-flex align-items-center py-5">
    <div class="container" style="max-width: 480px">
        <div class="text-center mb-4">
            <a href="{{ route('home') }}" class="d-inline-block">
                <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" width="56" height="56">
            </a>
        </div>

        @include('partials.flash')

        @yield('content')
    </div>
</main>

<footer class="text-center text-muted-soft small py-4 no-print">
    <a href="{{ route('home') }}" class="d-inline-block me-3">Beranda</a>
    <a href="{{ route('pages.about') }}" class="d-inline-block">Tentang</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script type="module" src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
