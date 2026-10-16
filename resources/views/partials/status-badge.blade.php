{{--
    Lencana status kegiatan.

    Warna datang dari enum EventStatus::tone() — komponen ini tidak
    pernah tahu apa arti "warning". Kalau status baru ditambahkan, cukup
    tambahkan nilai tone() di enum; tidak ada Blade yang perlu diubah.

    Warna tidak boleh jadi satu-satunya penanda: teks status selalu
    ikut tercetak, supaya tetap terbaca oleh pengguna dengan buta
    warna (Panduan WCAG AA).
--}}
@php
    $status = $event->status;
@endphp

<span class="badge-soft badge-soft--{{ $status->tone() }}" title="{{ $status->label() }}">
    {{ $status->label() }}
</span>
