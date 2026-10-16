{{--
    Label tenggat pendaftaran.

    Server sudah memutuskan tingkat urgensi (Event::deadlineTone()) dan
    menulis tanggal lengkap ke dalam elemen. JavaScript hanya menghitung
    mundur; kalau JavaScript tidak aktif, teksnya tetap benar karena
    tanggal penuh tercetak di sebelahnya.
--}}
@props(['event'])

{{--
    Tanggal lengkapnya ikut di dalam span, bukan di sebelahnya:
    JavaScript mencarinya dengan querySelector() dari dalam node itu, dan
    kalau letaknya di luar, tanggal lengkap tidak pernah sampai ke
    pembaca layar. Atribut `hidden` juga sengaja tidak dipakai: tanpa
    JavaScript, tanggal lengkapnya tidak akan pernah terbaca.
--}}
<span class="deadline deadline--{{ $event->deadlineTone() }}"
      data-countdown="{{ $event->registration_deadline->toIso8601String() }}"
      data-tone-on-expire="neutral"
      title="Tenggat: {{ $dateHelper->dateTime($event->registration_deadline) }}">
    <svg width="14" height="14" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-clock"></use></svg>
    <span>{{ $event->deadlineLabel() }}</span>
    <span class="visually-hidden" data-countdown-hint>
        Tenggat pendaftaran: {{ $dateHelper->longDate($event->registration_deadline) }} pukul {{ $event->registration_deadline->format('H.i') }} WIB
    </span>
</span>
