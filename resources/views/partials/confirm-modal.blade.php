{{--
    Modal konfirmasi untuk aksi yang merusak atau tidak bisa dibatalkan.

    Dipakai lewat:
        <button data-modal-open="konfirmasi-hapus">Hapus</button>
        @include('partials.confirm-modal', ['id' => 'konfirmasi-hapus',
                                            'title' => 'Hapus kegiatan?',
                                            'body' => '...'])

    Fokus dikembalikan ke pemicu setelah ditutup (lihat modal.js) — tanpa
    itu, orang yang memakai keyboard akan kehilangan tempatnya di halaman.
--}}
@props([
    'id' => 'konfirmasi',
    'title' => 'Yakin?',
    'body' => 'Tindakan ini tidak dapat dibatalkan.',
    'confirmLabel' => 'Ya, lanjutkan',
    'variant' => 'danger',
    'formId' => null,
])

<div class="modal-backdrop-lk" id="{{ $id }}" role="dialog" aria-modal="true"
     aria-labelledby="{{ $id }}-judul" aria-hidden="true">
    <div class="modal-box">
        <h2 class="h5 mb-3" id="{{ $id }}-judul">{{ $title }}</h2>
        <p class="text-muted-soft mb-4">{{ $body }}</p>
        <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-modal-close>Batal</button>
            {{--
                Kalau formId diisi, tombol ini mengirim form tersebut.
                Tanpa itu, tombol hanya menutup dialog dan orang mengira
                aksinya sudah terjadi padahal belum ada yang berubah.
            --}}
            <button type="button" class="btn btn-{{ $variant }}" data-modal-close
                    @if ($formId) data-modal-submit="{{ $formId }}" @endif>
                {{ $confirmLabel }}
            </button>
        </div>
    </div>
</div>
