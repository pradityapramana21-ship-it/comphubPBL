@extends('layouts.admin')

@section('title', 'Kategori')
@section('heading', 'Kategori Kegiatan')
@section('subheading', 'Kategori dinonaktifkan, bukan dihapus, supaya kegiatan lama tetap bisa dibaca.')

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <caption class="visually-hidden">Daftar kategori kegiatan</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kategori</th>
                            <th scope="col">Slug</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td class="fw-semibold">{{ $category->name }}</td>
                                <td><code class="small">{{ $category->slug }}</code></td>
                                <td class="text-end">
                                    @if ($category->is_active)
                                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                              data-confirm="Nonaktifkan kategori &quot;{{ $category->name }}&quot;? Kegiatan lama tidak akan hilang.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Nonaktifkan</button>
                                        </form>
                                    @else
                                        <span class="small text-muted-soft">Nonaktif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted-soft py-4">Belum ada kategori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="small text-muted-soft mt-3">
            Total {{ $total }} kategori termasuk yang dinonaktifkan.
        </p>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card__body">
                <h2 class="h6 mb-3">Tambah kategori</h2>

                <form method="POST" action="{{ route('admin.categories.store') }}" data-validate>
                    @csrf

                    @include('partials.form-field', [
                        'name' => 'name', 'label' => 'Nama kategori', 'required' => true,
                        'min' => 3, 'placeholder' => 'mis. Lomba',
                    ])

                    @include('partials.form-field', [
                        'name' => 'description', 'label' => 'Deskripsi',
                        'type' => 'textarea', 'maxlength' => 500, 'rows' => 3,
                    ])

                    <button type="submit" class="btn btn-primary">Tambah</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
