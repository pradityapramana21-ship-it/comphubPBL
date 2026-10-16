{{--
    Paginasi.

    Paginasi tidak memakai $paginator->links() bawaan karena markup-nya
    tidak memakai token warna di sini. Yang dipakai adalah
    linkCollection(): API publik yang mengembalikan daftar nomor halaman
    beserta penanda "..." — bukan `elements()`, yang protected dan
    berubah setiap versi Laravel.

    Partial ini juga tahan banting: kalau variabelnya ternyata bukan
    paginator (mis. karena ada controller yang keliru), komponen ini
    dilewati saja dan halaman tetap tampil. Paginasi bersifat kosmetik;
    ia tidak boleh menjatuhkan seluruh halaman.
--}}
@if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginator->hasPages())
    @php
        $semua = $paginator->linkCollection();
        $sebelumnya = $semua->first();
        $berikutnya = $semua->last();
        $nomor = $semua->slice(1, max($semua->count() - 2, 0));
    @endphp

    <nav class="mt-4" aria-label="Navigasi halaman">
        <ul class="pagination justify-content-center mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">Sebelumnya</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $sebelumnya['url'] }}" rel="prev">Sebelumnya</a>
                </li>
            @endif

            @foreach ($nomor as $link)
                @if ($link['label'] === '...')
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link" aria-hidden="true">&hellip;</span>
                        <span class="visually-hidden">Halaman lainnya</span>
                    </li>
                @elseif ($link['active'])
                    <li class="page-item active" aria-current="page">
                        <span class="page-link">{{ $link['label'] }}</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    </li>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $berikutnya['url'] }}" rel="next">Berikutnya</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">Berikutnya</span>
                </li>
            @endif
        </ul>
    </nav>
@endif