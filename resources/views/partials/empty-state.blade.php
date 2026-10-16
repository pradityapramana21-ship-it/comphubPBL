{{--
    Keadaan kosong.

    Selalu menjelaskan APA yang terjadi dan APA yang bisa dilakukan.
    "Tidak ada data" tanpa penjelasan membuat orang mengira sistemnya
    rusak.
--}}
@props([
    'icon' => 'empty-state',
    'title' => 'Belum ada apa-apa di sini',
    'text' => null,
])

<div class="empty-state">
    <img src="{{ asset('images/'.$icon.'.svg') }}" alt="" class="empty-state__icon">
    <p class="empty-state__title">{{ $title }}</p>
    @if ($text)
        <p class="empty-state__text">{{ $text }}</p>
    @endif
    @isset($slot)
        {{ $slot }}
    @endisset
</div>
