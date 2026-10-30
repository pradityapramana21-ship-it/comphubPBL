<article class="prose">
    <h1>Kebijakan privasi</h1>
    <p class="lead">Data yang disimpan, kegunaannya, dan cara meminta penghapusan.</p>

    <h2>Data yang disimpan</h2>
    <table>
        <thead><tr><th scope="col">Data</th><th scope="col">Kegunaan</th></tr></thead>
        <tbody>
            <tr><td>Nama dan email</td><td>Masuk ke akun dan menampilkan identitas akun.</td></tr>
            <tr><td>Kata sandi ter-hash</td><td>Memverifikasi proses masuk tanpa menyimpan kata sandi asli.</td></tr>
            <tr><td>Simpanan kegiatan</td><td>Menampilkan daftar tersimpan milik pengguna sendiri.</td></tr>
            <tr><td>Waktu notifikasi</td><td>Menentukan kegiatan baru sejak terakhir dibuka.</td></tr>
            <tr><td>Cookie sesi</td><td>Menjaga status masuk selama sesi aktif.</td></tr>
        </tbody>
    </table>

    <h2>Yang tidak dilakukan</h2>
    <ul>
        <li>Tidak mengirim email massal. Pengingat tenggat hanya muncul di dalam aplikasi.</li>
        <li>Tidak memakai pelacak pihak ketiga.</li>
        <li>Tidak menjual atau menyewakan data kepada pihak ketiga.</li>
    </ul>

    <h2>Kata sandi</h2>
    <p>Kata sandi disimpan sebagai hash satu arah. Pengelola aplikasi tidak dapat membaca kata sandi Anda. Jika lupa, gunakan <a href="<?= e(url('/lupa-sandi')) ?>">atur ulang kata sandi</a>.</p>

    <h2>Retensi dan penghapusan</h2>
    <p>Kegiatan yang sudah terbit diarsipkan agar riwayat katalog tetap dapat ditelusuri. Untuk meminta penghapusan akun atau data, gunakan <a href="<?= e(url('/kontak')) ?>">halaman kontak</a>.</p>
</article>
