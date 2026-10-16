{{--
    Pesan singkat (toast).

    Hanya untuk pesan yang TIDAK memerlukan tindakan. Kalau pengguna harus
    membuat keputusan, pakai confirm-modal — toast hilang sendiri dan
    keputusan tidak bisa diambil darinya.
--}}
@foreach (['success' => 'success', 'error' => 'danger', 'status' => 'info'] as $key => $tone)
    @if (session($key))
        <div class="toast toast--{{ $tone }}" data-toast role="status">
            <div>{{ session($key) }}</div>
            <button type="button" class="btn-close btn-close-sm ms-auto" data-toast-dismiss aria-label="Tutup"></button>
        </div>
    @endif
@endforeach
