{{--
    Kartu kegiatan di daftar.

   /cards dipanggil dari tiga tempat (beranda, katalog, profil penyelenggara)
    supaya satu kegiatan terlihat sama di mana pun.

    $savedMap: array<int, bool> — diisi sekali per halaman lewat
    BookmarkService::savedMap() supaya tidak ada satu query per kartu.
--}}
@props(['event', 'savedMap' => []])

@php
    $isSaved = (bool) ($savedMap[$event->id] ?? false);
    $isOnline = $event->mode->value === 'online';
@endphp

<article class="card card--hoverable event-card">
    <div class="event-card__body">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <span class="badge-soft badge-soft--{{ $event->category->slug === 'lomba' ? 'primary' : ($event->category->slug === 'webinar' ? 'info' : 'neutral') }}">
                {{ $event->category->name }}
            </span>

            @auth
                <button type="button"
                        class="btn btn-sm btn-link p-0 text-secondary"
                        data-bookmark-url="{{ route('events.bookmark.store', $event) }}"
                        data-saved="{{ $isSaved ? '1' : '0' }}"
                        aria-pressed="{{ $isSaved ? 'true' : 'false' }}"
                        aria-label="{{ $isSaved ? 'Hapus dari Tersimpan' : 'Simpan kegiatan' }}">
                    <svg width="18" height="18" aria-hidden="true"
                         data-bookmark-icon fill="{{ $isSaved ? 'currentColor' : 'none' }}">
                        <use href="{{ asset('images/icon-sprite.svg') }}#i-bookmark"></use>
                    </svg>
                    <span class="visually-hidden" data-bookmark-label>{{ $isSaved ? 'Tersimpan' : 'Simpan' }}</span>
                </button>
            @endauth
        </div>

        <h3 class="event-card__title">
            <a href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
        </h3>

        <div class="event-card__meta">
            <span>
                <svg width="15" height="15" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-calendar"></use></svg>
                {{ $dateHelper->date($event->event_start_at) }}
            </span>
            <span>
                <svg width="15" height="15" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-{{ $isOnline ? 'globe' : 'pin' }}"></use></svg>
                {{ $event->mode->label() }}
            </span>
        </div>

        <p class="mb-0 small text-muted-soft">
            {{ \Illuminate\Support\Str::limit(strip_tags($event->description), 110) }}
        </p>

        <div class="event-card__footer">
            <span class="event-card__provider" title="{{ $event->providerProfile->organization_name }}">
                <svg width="15" height="15" aria-hidden="true"><use href="{{ asset('images/icon-sprite.svg') }}#i-shield"></use></svg>
                <span class="text-truncate">{{ \Illuminate\Support\Str::limit($event->providerProfile->organization_name, 24) }}</span>
            </span>

            @include('partials.deadline-timer', ['event' => $event])
        </div>
    </div>
</article>
