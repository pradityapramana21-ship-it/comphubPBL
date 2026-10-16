@extends('layouts.app')

@section('title', 'Kebijakan Privasi')

@section('content')
<div class="container" style="max-width: var(--container-narrow);">
    <h1>Kebijakan privasi</h1>
    <p class="lead">
        Ringkasan data yang kami kumpulkan, untuk apa, dan bagaimana dilindungi.
    </p>

    <h2 class="h5 mt-5 mb-2">Data yang dikumpulkan</h2>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    <th scope="col">Kegunaan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nama dan email</td>
                    <td>Masuk ke akun, dan untuk komunikasi tentang pengajuan kegiatan.</td>
                </tr>
                <tr>
                    <td>Data organisasi</td>
                    <td>Verifikasi sebelum organisasi boleh menerbitkan kegiatan.</td>
                </tr>
                <tr>
                    <td>Bookmark</td>
                    <td>Menampilkan daftar "Tersimpan" milik Anda sendiri.</td>
                </tr>
                <tr>
                    <td>Dokumen verifikasi</td>
                    <td>Diperiksa administrator. Disimpan di luar jangkauan web.</td>
                </tr>
                <tr>
                    <td>Jejak audit</td>
                    <td>Mencatat tindakan admin, untuk investigasi bila ada masalah.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2 class="h5 mt-4 mb-2">Yang tidak kami lakukan</h2>
    <div class="card">
        <div class="card__body">
            <ul class="mb-0">
                <li class="mb-2">
                    Kami <strong>tidak pernah</strong> mengirim email massal. Pengingat
                    tenggat hanya muncul di dalam aplikasi.
                </li>
                <li class="mb-2">
                    Identitas orang yang melapor kegiatan <strong>tidak pernah</strong>
                    dibagikan kepada penyedia kegiatan. Admin melihatnya; penyedia tidak.
                </li>
                <li>
                    Kami tidak menjual atau menyewakan data siapa pun kepada pihak ketiga.
                </li>
            </ul>
        </div>
    </div>

    <h2 class="h5 mt-5 mb-2">Kata sandi</h2>
    <p>
        Kata sandi disimpan dalam bentuk hash satu arah. Artinya, bahkan pengelola
        aplikasi ini tidak bisa membaca kata sandi Anda. Kalau lupa, gunakan
        <a href="{{ route('auth.password.request') }}">aturan ulang kata sandi</a>.
    </p>

    <h2 class="h5 mt-4 mb-2">Jejak audit</h2>
    <p>
        Tindakan administratif seperti menyetujui kegiatan, menolak pengajuan, atau
        menangguhkan akun dicatat lengkap&mdash;siapa, kapan, dan terhadap apa.
        Data sensitif seperti kata sandi dan token <strong>tidak pernah</strong>
        masuk ke dalam log tersebut.
    </p>

    <h2 class="h5 mt-4 mb-2">Menghapus akun</h2>
    <p>
        Hubungi administrator lewat <a href="{{ route('pages.contact') }}">halaman kontak</a>.
        Kegiatan yang sudah terbit tidak dihapus &mdash; ia diarsipkan agar jejaknya
        tetap bisa ditelusuri.
    </p>
</div>
@endsection
