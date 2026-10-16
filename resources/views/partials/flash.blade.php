{{--
    Pesan dari server.

    Status, sukses, dan error berasal dari flash session yang diisi
    controller, bukan dari tebakan JavaScript.

    Aturan penting: JANGAN pernah memasukkan kode HTML ke dalam pesan.
    Isi pesan ditulis apa adanya, jadi HTML di dalamnya akan dieksekusi
    browser. Semua pesan harus berupa teks biasa.
--}}
@php
    $flashKey = 'success';
    $flashMessage = session($flashKey);
@endphp

<div class="no-print">
    @foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $key => $variant)
        @if (session($key))
            <div class="alert alert-{{ $variant }} alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
                <div>{{ session($key) }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        @endif
    @endforeach

    @foreach (['status', 'notices'] as $key)
        @if (session($key))
            <div class="alert alert-info d-flex gap-2 align-items-start" role="status">
                <div>{{ session($key) }}</div>
            </div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold mb-2">Periksa kembali isian Anda:</p>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
