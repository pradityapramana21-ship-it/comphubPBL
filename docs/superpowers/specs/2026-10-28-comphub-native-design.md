# CompHub Native — Design

Tanggal: 2026-10-28
Status: menunggu persetujuan
Sumber lama: `/home/me404/Mode_Larp_Frontend/revenge` (Laravel 13, tetap utuh)

## 1. Maksud

Menghasilkan ulang CompHub sebagai aplikasi PHP native terstruktur di
`/home/me404/Mode_Larp_Frontend/revenge-native`, tanpa Laravel dan tanpa
Composer, dengan fitur yang sama, data kegiatan nyata yang terverifikasi,
alur tamu yang jelas, dan UI glassmorphism yang disengaja.

Keberhasilan berarti:

- Semua rute dan peran lama tetap bekerja.
- Tidak ada data demo, data karangan, atau tautan pendaftaran palsu.
- Tamu dapat membaca seluruh informasi kegiatan tanpa akun.
- Akses lanjutan (tautan pendaftaran, simpan, lapor) baru terbuka setelah masuk.
- Tampilan baru terasa seperti produk, bukan templat AI.
- Aplikasi berjalan dengan `php -S` dan PostgreSQL tanpa `composer install`.

## 2. Keputusan yang sudah diambil

| Topik | Keputusan |
|---|---|
| Arti native | PHP murni, tanpa Laravel dan tanpa Composer |
| Database | PostgreSQL, skema lama dipertahankan |
| Lokasi | Folder baru `revenge-native`; repo lama tidak disentuh |
| Data | Hanya kegiatan nyata yang terverifikasi masih buka |
| Data demo | Dihapus semua; satu admin asli dibuat lewat CLI/env |

## 3. Ruang lingkup

### 3.1 Ditulis ulang

- Front controller, router, middleware, request/response, view renderer.
- Sesi, CSRF, autentikasi, otorisasi berbasis peran, reset kata sandi.
- Repository PDO untuk seluruh entitas.
- Service layer: auth, event, bookmark, report, moderation, verification,
  audit, expiry.
- Tujuh aturan moderasi dan agregasinya.
- Semua halaman publik, auth, pengguna, penyedia, dan admin.
- Aset CSS/JS vanilla tanpa build step.
- CLI: migrasi, buat admin, impor kegiatan, kedaluwarsa, tes.

### 3.2 Tetap

- Skema data dan constraint PostgreSQL.
- Nama rute, parameter, dan pembatasan akses.
- Perilaku moderasi, verifikasi penyedia, laporan, dan audit.
- Aturan tanggal, status, dan kepemilikan kegiatan.
- Tanpa npm/Vite.

### 3.3 Tidak termasuk

- Scraping Instagram langsung (butuh login dan melanggar ToS).
- Notifikasi email nyata di luar driver log.
- Pembayaran, pendaftaran peserta di dalam platform, atau chat.
- Dark mode.
- Fitur baru di luar permintaan.

## 4. Arsitektur native

```
revenge-native/
  public/                 # document root
    index.php             # front controller
    assets/css|js|img
  src/
    Core/                 # App, Router, Request, Response, View, Session,
                          # Csrf, Auth, Database, Validator, RateLimiter,
                          # Cache, Mailer, Redirect, Url
    Http/Controllers/     # Public, Auth, User, Provider, Admin
    Http/Middleware/
    Http/Requests/        # validasi per form
    Models/               # entitas ringan + aturan domain
    Repositories/         # kontrak + implementasi PDO
    Services/             # logika aplikasi
    Services/Moderation/
    Support/
    Enums/
  resources/views/        # template PHP biasa, layout + partial
  routes/web.php          # definisi rute
  database/schema.sql     # skema PostgreSQL
  database/seed/categories.sql
  data/events/*.json      # data impor dengan provenance
  bin/console             # CLI
  storage/                # log, cache, upload privat
  tests/                  # runner assert sederhana
  .env.example
  README.md
```

Prinsip:

- Satu front controller; tidak ada file PHP yang bisa diakses langsung dari web.
- Semua query memakai prepared statement PDO.
- Semua output di-escape lewat helper `e()`.
- Semua POST/PUT/PATCH/DELETE memakai token CSRF.
- Semua transisi status memakai transaksi database.
- Tidak ada ORM; repository mengembalikan objek entitas ringan atau array.

### 4.1 Autoload

Autoloader sendiri di `src/Core/Autoloader.php` dengan pemetaan PSR-4
`App\` ke `src/`. Tidak memakai Composer.

### 4.2 Sesi dan autentikasi

- Sesi file native PHP, cookie `HttpOnly`, `SameSite=Lax`, `Secure` bila HTTPS.
- Regenerasi ID sesi saat login dan logout.
- `password_hash` / `password_verify` dengan `PASSWORD_DEFAULT`.
- Reset kata sandi memakai token acak 32 byte yang di-hash dan disimpan di
  `password_reset_tokens`; tautan ditulis ke `storage/logs/mail.log`
  (driver `log`, sama seperti `.env` lama).
- Login dan reset dibatasi laju per IP + email.

### 4.3 Otorisasi

- Middleware `auth`, `guest`, `role:*`, `verified`, `active`.
- Policy setara lama untuk event, laporan, profil penyedia, dan pengguna.
- Rute privat memakai ID eksplisit; rute publik memakai slug.
- IDOR dicegah dengan memeriksa kepemilikan di repository/service, bukan
  hanya di tampilan.

### 4.4 Validasi

Validator deklaratif: required, string, email, url, integer, in, max, min,
date, after, before, same, unique. Pesan bahasa Indonesia. Aturan lintas
field (deadline <= mulai, akhir >= mulai, lokasi wajib untuk offline/hybrid)
ditegakkan di validator dan constraint database.

### 4.5 Rate limit

Penghitung berbasis file di `storage/cache/rate-limit/` dengan kunci
`route:ip:identity`. Batas mengikuti kebijakan lama: login, register,
bookmark, report, submit, global. Dibersihkan otomatis saat kedaluwarsa.

### 4.6 Mailer

Antarmuka `Mailer` dengan driver `log` (default) dan `mail` (opsional lewat
konfigurasi). Tidak ada dependensi SMTP eksternal.

### 4.7 CLI

`bin/console` mendukung:

- `migrate` — jalankan `database/schema.sql` idempoten.
- `create-admin` — buat admin dari `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
- `import-events <file>` — impor idempoten dari JSON.
- `expire-events` — tandai kegiatan lewat tenggat.
- `test` — jalankan runner tes.

## 5. Database

Database baru: `lomba_kampus_native` (dev) dan `lomba_kampus_native_test`.
Database lama tidak diubah.

Skema lama dipertahankan: users, password_reset_tokens, sessions, cache,
jobs, categories, provider_profiles, events, bookmarks, moderation_logs,
event_reports, audit_logs, beserta CHECK constraint dan indeks.

Tambahan kecil yang dibutuhkan untuk kejujuran data:

| Kolom | Tipe | Alasan |
|---|---|---|
| `events.organizer_name` | varchar(180) null | Nama penyelenggara asli untuk kegiatan kurasi |
| `events.source_url` | varchar(500) null | Halaman sumber yang diperiksa |
| `events.source_checked_at` | timestamptz null | Waktu verifikasi terakhir |
| `events.source_note` | text null | Catatan keraguan, mis. "tanggal tutup belum dipastikan" |

Aturan:

- Kegiatan kurasi dimiliki satu akun sistem `kurasi@internal.invalid` dengan
  kata sandi acak yang tidak pernah dicetak, dan profil `Tim Kurasi CompHub`
  (`campus_name` = "Lintas kampus", `official_email` = alamat internal).
  Akun ini tidak dapat dipakai login, tidak muncul di direktori penyedia,
  dan tidak dapat diubah lewat manajemen pengguna.
- Badge kegiatan kurasi berbunyi **"Kurasi"**, bukan "Terverifikasi".
  Halaman penyelenggara menjelaskan bahwa tim kurasi bukan penyelenggara.
- `organizer_name` yang ditampilkan adalah penyelenggara asli.
- Tidak ada event tanpa `source_url` dan `source_checked_at` kecuali event
  yang dibuat penyedia terverifikasi lewat alur normal.
- Kategori awal: Lomba, Webinar, Seminar. Ini taksonomi, bukan data demo.
- Tidak ada seeder akun/event/laporan fiktif.
- Admin pertama dibuat dari env; tidak ada kata sandi contoh di README.

## 6. Data nyata

### 6.1 Sumber

1. `/home/me404/test/lomba.pdf` — ICETISI 2027, Seno Hackathon ITS 2026,
   webinar InGuide, dan daftar yang disaring.
2. Situs resmi penyelenggara.
3. Agregator (KabarLomba, HMTI Udayana, KelasRiset) hanya untuk penemuan;
   setiap entri wajib punya tautan resmi atau tautan pendaftaran yang dapat
   dibuka.

Instagram tidak di-scrape. Bila satu-satunya sumber adalah posting
Instagram, entri tidak dimasukkan kecuali ada tautan pendaftaran resmi yang
bisa diverifikasi.

### 6.2 Syarat masuk

- Tenggat pendaftaran masih di masa depan saat impor.
- Nama penyelenggara jelas.
- Tanggal, mode, lokasi, dan tautan pendaftaran terbaca.
- Sumber dicatat: URL, waktu periksa, dan catatan bila ada keraguan.
- Entri yang tanggalnya tidak pasti diberi catatan "tanggal tutup belum
  dipastikan" dan tidak boleh tampil sebagai "buka" tanpa dasar.

### 6.3 Format impor

`data/events/2026-10-28.json` berisi array objek dengan kunci:

```
title, slug, category, organizer, description, requirements,
registration_url, source_url, source_checked_at, source_note,
mode, location_text, platform_url, event_start_at, event_end_at,
registration_deadline, status
```

Importer memvalidasi dan menulis dalam satu transaksi. Identitas entri
adalah `slug`:

- Slug baru: sisipkan.
- Slug yang sudah ada dan dimiliki akun kurasi: perbarui seluruh kolom
  kurasi, termasuk `source_checked_at`.
- Slug yang sudah ada dan dimiliki penyedia lain: tolak, jangan sentuh.
- `registration_url` dan `source_url` boleh sama antar-entri (mis. portal
  bersama atau konferensi tahunan); importer mencatat peringatan, bukan
  menolak.
- Entri tanpa `source_url`, `source_checked_at`, atau penyelenggara ditolak.
- Tidak ada data contoh di file ini.

### 6.4 Kandidat awal (wajib diverifikasi ulang saat impor)

Lomba: PERTAPSI Tax Competition 2026; IT-CAS 2026; GenBI Essay Innovation
2026; Business Plan Competition Fisipreneur 2026; LKTIN EcoGeneration 2026;
Festival Bela Negara Menwa UNM; LOGICODIX Hackathon 2026; Kompetisi Esai
Nasional HMTI Udayana; Seno Hackathon ITS 2026.

Seminar: EDUPRO 2026; ICDESS 2026; ICETISI 2027.

Webinar: KelasRiset 2 November 2026; IFERP November 2026; Webinar Beasiswa
30 November 2026; InGuide November 2026.

Kandidat yang sudah tutup atau tidak dapat diverifikasi tidak dimasukkan.

## 7. Alur tamu dan pengguna

### 7.1 Tamu

- Beranda, katalog, filter, pencarian, detail kegiatan, direktori penyedia,
  dan halaman statis terbuka penuh.
- Detail menampilkan semua informasi: penyelenggara, deskripsi, syarat,
  tanggal, mode, lokasi, kategori, catatan sumber, dan status tenggat.
- `registration_url` dan `source_url` TIDAK dikirim ke HTML tamu, metadata
  OG, JSON, atau atribut mana pun. Yang tampil hanya nama domain sumber dan
  tanggal pemeriksaan sebagai teks. Gerbang ini nyata, bukan sekadar
  menyembunyikan tombol.
- Penggantinya: panel "Lanjutkan pendaftaran" dengan penjelasan bahwa akun
  diperlukan untuk membuka tautan resmi dan menyimpan kegiatan.
- Tombol Simpan dan Laporkan mengarah ke `/masuk?next=...`, bukan error.
- Setelah daftar/masuk, pengguna kembali ke halaman detail semula.

### 7.2 Pengguna masuk

- Detail menampilkan tombol "Lanjut ke pendaftaran" yang membuka
  `registration_url` di tab baru dengan `rel="noopener noreferrer"` dan
  peringatan tautan eksternal.
- Simpan, lapor, profil, dan dasbor aktif.
- `next` hanya menerima path lokal; URL absolut, `//`, dan skema asing ditolak.

### 7.3 Penyedia dan admin

- Alur lama dipertahankan: daftar, verifikasi, buat kegiatan, ajukan,
  moderasi, laporan, kategori, pengguna, audit.
- Penyedia belum terverifikasi dapat memperbaiki profil, tetapi tidak dapat
  membuat atau mengajukan kegiatan.
- Admin melihat sumber kurasi pada detail moderasi.

## 8. Arah UI

### 8.1 Proporsi 60/30/10

- 60% kanvas netral: `#F2F4F7` dengan tekstur halus dan cahaya dingin.
- 30% tinta/slate: `#111827` untuk navigasi, footer, sidebar, teks utama.
- 10% aksen: `#2563EB` untuk CTA, tautan, fokus, dan penanda aktif.

Warna status tetap semantik: hijau `#15803D`, kuning `#B45309`, merah
`#B91C1C`, biru informasi `#0369A1`. Tidak ada gradien ungu, blob, atau
teks gradien.

### 8.2 Glassmorphism yang disengaja

- Kaca hanya untuk navbar lengket, panel filter, kartu kegiatan di atas
  kanvas, sheet, dan modal.
- Resep: `background: rgba(255,255,255,.62)`, `backdrop-filter: blur(16px)`,
  border 1px `rgba(17,24,39,.10)`, inner highlight 1px putih, shadow rendah.
- Tidak semua kotak memakai kaca. Tabel admin, form, dan isi utama memakai
  permukaan solid agar tetap terbaca.
- Tidak ada animasi kaca berlebihan; transisi 160–220 ms, hormati
  `prefers-reduced-motion`.

### 8.3 Tipografi dan layout

- Font sistem: `ui-sans-serif`, `system-ui`, `Segoe UI`, `Inter`, sans-serif.
- Skala 12/14/16/18/24/32/44/56, line-height 1.5.
- Grid 12 kolom, lebar maksimum 1200 px, ritme spasi kelipatan 4 px.
- Beranda asimetris: hero 7/5, kartu tenggat, kategori, lalu katalog.
- Kartu kegiatan: kategori, judul, penyelenggara, tanggal, tenggat, mode,
  dan aksi. Tanpa gambar stok; aksen warna per kategori.
- Admin: kepadatan tinggi, tabel solid, filter jelas, aksi berbahaya
  memakai konfirmasi.

### 8.4 Aksesibilitas

- Kontras WCAG 2.2 AA; fokus terlihat; target sentuh minimal 44 px.
- Satu `h1` per halaman, landmark, skip link, label form eksplisit.
- Status tidak hanya warna; selalu ada teks.
- Tabel memakai caption dan header yang benar.
- Modal menahan fokus dan dapat ditutup dengan Escape.

## 9. Parity fitur

| Area | Fitur |
|---|---|
| Publik | Beranda, katalog, filter kategori/mode/status, pencarian, urutan, paginasi, detail, penyelenggara, lima halaman statis, 403/404/419/429/500 |
| Auth | Daftar pengguna, daftar penyedia, masuk, keluar, lupa sandi, atur sandi |
| Pengguna | Dasbor, tersimpan, profil, ganti sandi, buat laporan, tarik laporan |
| Penyedia | Dasbor, profil organisasi, unggah dokumen verifikasi, daftar kegiatan, buat, sunting, hapus, ajukan, lihat |
| Admin | Dasbor, antrean moderasi, detail, keputusan, verifikasi penyedia, unduh dokumen verifikasi, laporan, pengguna, kategori, audit |

Dokumen verifikasi disimpan di luar webroot dengan nama acak. Hanya pemilik
profil dan admin yang dapat mengunduhnya lewat rute khusus; tipe MIME
dibatasi PDF/JPG/PNG dan ukuran maksimal 2 MB. Path tidak pernah diterima
dari input pengguna.

## 10. Tes dan verifikasi

Tanpa PHPUnit. Runner `tests/run.php` memakai assert dan exit code.

Cakupan minimal:

- Unit: tujuh aturan moderasi, agregasi skor, slug, tanggal, normalisasi.
- Integrasi: daftar/masuk, sesi, CSRF, reset sandi, simpan, lapor, alur
  penyedia, moderasi admin, verifikasi penyedia, audit.
- Database: constraint status, tanggal, lokasi, verifikasi, unik slug.
- HTTP: smoke test semua rute utama dengan server bawaan PHP dan curl,
  termasuk tamu vs masuk vs peran.
- Data: impor menolak entri tanpa sumber atau tanggal lampau, memperbarui
  entri kurasi, dan tidak menyentuh entri milik penyedia.
- Keamanan: tamu tidak menerima URL pendaftaran/sumber di HTML, unduhan
  dokumen menolak peran lain, dan `next` menolak URL eksternal.

Perintah bukti:

```
php bin/console test
php bin/console migrate
php bin/console import-events data/events/2026-10-28.json
php -S 127.0.0.1:8080 -t public
```

## 11. Risiko dan mitigasi

| Risiko | Mitigasi |
|---|---|
| Tanpa framework, permukaan keamanan lebih besar | CSRF, prepared statement, escaping, policy, uji peran |
| Kehilangan ORM | Repository PDO terpusat, transaksi eksplisit |
| Data cepat basi | `source_checked_at`, catatan tanggal, impor ulang |
| Instagram tidak dapat diakses | Sumber resmi dan agregator sebagai penemuan |
| Email hanya log | Ditulis jelas di README; reset tetap dapat diuji |
| Rewrite memutus fitur | Parity matrix, tes integrasi, perbandingan rute lama |

## 12. Rollback

Aplikasi lama tetap ada dan tidak diubah. Rollback = jalankan aplikasi lama
dengan database lamanya. Database native terpisah.

## 13. Kriteria penerimaan

1. `php -S` menyajikan aplikasi tanpa Composer dan tanpa Laravel.
2. Semua rute di parity matrix merespons dan menjaga peran.
3. Tamu dapat membaca detail kegiatan; akses lanjutan mengarah ke daftar.
4. Pengguna masuk mendapat tautan pendaftaran eksternal yang benar.
5. Tidak ada akun, event, laporan, atau audit demo.
6. Setiap event kurasi punya penyelenggara asli dan sumber.
7. Tidak ada tautan pendaftaran yang tidak dapat dibuka.
8. UI memakai proporsi 60/30/10, kaca terbatas, kontras AA.
9. `php bin/console test` lulus.
10. README menjelaskan setup, admin, impor, dan batasan.

## 14. Yang diserahkan ke builder

- Nama kelas dan pemecahan file selama kontrak rute dan perilaku tetap.
- Detail kecil komponen UI selama arah 60/30/10 dan aturan kaca diikuti.
- Jumlah event akhir, selama hanya entri terverifikasi yang masuk.
- Penyesuaian pesan validasi selama maknanya sama.

Keputusan yang saya ambil setelah review rancangan:

1. Kurasi memakai akun sistem tak-dapat-login dan badge "Kurasi", tanpa
   mengubah nullable `provider_profiles.user_id`.
2. Gerbang tamu bersifat keras: URL pendaftaran dan sumber tidak dikirim ke
   tamu sama sekali.
3. Tujuh aturan moderasi lama adalah sumber kebenaran; tidak ada aturan
   kedelapan.
4. `source_note` menjadi kolom keempat dan terlihat oleh tamu.
5. Impor memakai `slug` sebagai identitas; hanya baris kurasi yang boleh
   diperbarui.
6. Unggah dokumen verifikasi tetap ada dan mendapat rute unduh aman untuk
   pemilik serta admin.

---

## Amendemen 2026-10-29 — Gerbang tamu, notifikasi, ruang lingkup

Status: disetujui lewat jawaban pengguna pada 2026-10-29. Bagian di atas tetap
berlaku kecuali yang diubah di bawah.

### A. Gerbang tamu diperketat

Tamu melihat ringkasan di beranda dan katalog. Membuka halaman detail
mengarahkan tamu ke login. Detail penuh (deskripsi, syarat, tanggal rinci,
tautan pendaftaran, sumber, dan simpan) hanya untuk pengguna masuk.

- `GET /kegiatan/{slug}` untuk tamu: `302` ke `/masuk?next=/kegiatan/{slug}`.
- Kartu tamu menampilkan judul, kategori, penyelenggara, jadwal, tenggat,
  status, dan ringkasan singkat. Tidak ada tautan detail yang bisa dibuka
  tanpa login.
- Halaman katalog `/kegiatan` tetap publik dan hanya berisi ringkasan.
- Klik "Simpan" dan "Lihat detail" untuk tamu mengarah ke login.

### B. Notifikasi dalam aplikasi

Tidak ada email dan tidak ada SMTP.

- `users.events_seen_at TIMESTAMPTZ NULL` menyimpan waktu terakhir pengguna
  membuka halaman notifikasi.
- Kegiatan baru = `events.status = 'approved'` dan
  `events.published_at > users.events_seen_at`.
- Lonceng di navigasi menampilkan jumlah kegiatan baru.
- `/notifikasi` menampilkan daftar kegiatan baru.
- `POST /notifikasi/tandai-dibaca` mengatur `events_seen_at = now()`.
- Pengguna baru mendapat `events_seen_at = now()` saat mendaftar, sehingga
  tidak menerima tumpukan notifikasi lama.

### C. Ruang lingkup sekarang

Dikerjakan: publik, autentikasi pengguna, simpan, dasbor pengguna sederhana,
notifikasi, importer data, dan CLI pendukung.

Ditunda: dasbor penyedia, dasbor admin, moderasi, verifikasi penyedia,
laporan, audit, dan unduhan dokumen. Rute penyelenggara disembunyikan dari
navigasi dan pendaftaran penyelenggara belum dibuka.

### D. Data

- Hanya entri yang bisa diverifikasi dari `lomba.pdf` dan URL resmi yang bisa
  dibuka. Kegiatan yang sudah tutup boleh masuk sebagai arsip.
- Semua audiens boleh: mahasiswa, profesional, dan umum. Tidak ada kolom
  audiens.
- Setiap entri kurasi wajib punya `organizer_name`, `source_url`,
  `source_checked_at`, dan `registration_url`.
- `events.deadline_confirmed BOOLEAN NOT NULL DEFAULT TRUE` menandai tenggat
  yang belum dipastikan. Bila `FALSE`, UI menampilkan "Tenggat belum
  dipastikan" dan tidak mengklaim status "Buka" sebagai fakta.
- Dataset awal yang sudah diverifikasi:
  - ICETISI 2027 — seminar, 8–9 Februari 2027, Jakarta, tenggat paper
    30 November 2026, sumber `https://icetisi.unas.ac.id/icetisi-2027/`.
  - Hackathon ITS 2026 — lomba, November 2026, tenggat belum dipastikan,
    sumber `https://hackathon-its.id`.
  - INGUIDE Course 2 — webinar, 11/12/18 November 2026, daring,
    sumber `https://inguide.org/events/course-2-certified-guideline-methodologist-upcoming-webinars/`.

### E. UI

Pertahankan proporsi 60/30/10 dan glass terbatas. Beranda menjadi daftar
informasi lomba: hero ringkas, filter kategori, kartu dengan tanggal, tenggat,
status, penyelenggara, dan ringkasan. Tidak menambah gambar stok, gradien
ungu, blob, atau animasi berlebihan.

### F. Kriteria penerimaan tambahan

1. Tamu yang membuka detail mendapat `302` ke login, bukan halaman detail.
2. Pengguna masuk melihat detail penuh dan tautan pendaftaran.
3. Lonceng dan badge notifikasi hanya muncul untuk pengguna masuk.
4. Tandai dibaca menghapus badge.
5. Setiap entri kurasi punya provenance lengkap.
6. `php bin/console test` lulus.
