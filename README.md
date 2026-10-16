# LombaKampus — Website Informasi Lomba, Webinar, dan Seminar

Project Based Learning (PBL) — tim 5 orang.
Platform katalog kegiatan mahasiswa dengan verifikasi penyelenggara, moderasi berlapis, dan anti-spam otomatis.

> Panduan lengkap: `docs/` · Baseline teknis: Panduan PBL Bab 1–12

---

## Prasyarat

| Kebutuhan | Versi | Cara cek |
|---|---|---|
| PHP | **8.3+** (8.5 sudah diuji) | `php -v` |
| Composer | 2.x | `composer -V` |
| PostgreSQL | 14+ (18.6 sudah diuji) | `psql --version` |
| Extension PHP | `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `ctype`, `dom`, `fileinfo`, `curl` | `php -m` |

**Docker tidak dipakai.** Proyek ini berjalan langsung di PHP host + PostgreSQL lokal supaya tidak perlu `sudo` dan tidak ada build step.

### Extension yang belum ada di mesin ini (opsional)

`bcmath`, `intl`, dan `gd` belum terpasang. Proyek tetap jalan tanpa ketiganya.
Install hanya bila nanti ada fitur yang butuh (mis. manipulasi gambar):

```bash
sudo pacman -S php-bcmath php-intl php-gd
```

---

## Setup ( sekali, untuk setiap anggota tim )

### 1. Clone & install dependensi

```bash
cd revenge
composer install
```

### 2. Siapkan konfigurasi

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Siapkan database PostgreSQL

Buat role (bukan superuser — Panduan §9.2 melarang akun superuser di produksi):

```sql
CREATE ROLE lomba_app LOGIN PASSWORD 'lomba_dev_password';
CREATE DATABASE lomba_kampus_dev  OWNER lomba_app ENCODING 'UTF8';
CREATE DATABASE lomba_kampus_test OWNER lomba_app ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE lomba_kampus_dev  TO lomba_app;
GRANT ALL PRIVILEGES ON DATABASE lomba_kampus_test TO lomba_app;
```

> **Kendala tiap anggota mungkin berbeda.** Jika salah satu anggota tidak boleh
> memakai akun `postgres`, buat role sendiri lalu sesuaikan `DB_USERNAME` /
> `DB_PASSWORD` di `.env`-nya. Folder `.env` tidak pernah masuk Git, jadi
> setiap orang bebas punya kredensial lokal yang berbeda.

### 4. Migrasi & data demo

```bash
php artisan migrate --seed
```

### 5. Jalankan

```bash
php artisan serve
```

Buka <http://localhost:8000>.

### 6. Test

```bash
php artisan test
```

Test memakai database **`lomba_kampus_test`** yang terpisah, supaya data demo
tidak ikut terhapus oleh `RefreshDatabase`.

---

## Tidak ada build step

Proyek ini sengaja **tanpa Vite/npm**:

- CSS: Bootstrap 5 via CDN + `public/css/tokens.css` (design tokens)
- JS: vanilla per fitur di `public/js/`
- Layout memuat aset lewat `{{ asset('css/app.css') }}`, **bukan** directive `@vite`

Konsekuensinya `package.json`, `node_modules`, dan `vite.config.js` tidak ada.
Menambah CSS atau JS = membuat file di `public/`, lalu menyinkronkan `<link>`
di `resources/views/layouts/app.blade.php`. Tidak perlu `npm run build` sama sekali.

---

## Akun demo

Setelah `php artisan migrate --seed`:

| Peran | Email | Password | Condition |
|---|---|---|---|
| Admin | `admin@lomba.test` | `password` | Lihat seluruh antrean |
| Admin | `admin2@lomba.test` | `password` | Admin kedua, untuk menguji jejak audit |
| Provider | `bem@lomba.test` | `password` | **Terverifikasi** |
| Provider | `himpunan@lomba.test` | `password` | **Menunggu verifikasi** |
| Provider | `salsa@lomba.test` | `password` | **Ditolak**, dengan alasan tersimpan |
| User | `andi@lomba.test` | `password` | Punya bookmark |
| User | `budi@lomba.test` | `password` | Punya laporan terbuka |
| User | `citra@lomba.test` | `password` | Akun biasa |
| User | `dina@lomba.test` | `password` | **Ditangguhkan** |

Seluruh akun bersifat non-produksi. Jangan pernah memakai kredensial ini di server publik.

### Isi data demo

Data demo sengaja mencakup setiap status, supaya fitur moderasi bisa
didemokan tanpa membuat data baru:

| Bagian | Isi |
|---|---|
| Kategori | Lomba, Webinar, Seminar, Workshop |
| Kegiatan tayang | 4 kegiatan approved, satu di antaranya tenggatnya besok |
| Antrean admin | 2 pengajuan pending, 1 di antaranya ditandai otomatis (skor 10) |
| Contoh ditolak | 1 kegiatan spam yang kena pola "bayar sebelum daftar" dan tautan pemendek (skor 100) |
| Lainnya | 1 changes_requested, 2 draf, 1 kedaluwarsa, 1 diarsipkan |
| Jejak | 3 status verifikasi, 3 bookmark, 1 laporan terbuka, 7 entri moderasi, 5 jejak audit |

Angka skor pada data demo dihitung ulang oleh `ModerationService` saat seed,
jadi tidak pernah berbeda dari apa yang sistemnya benar-benar lakukan.

---

## Struktur direktori

```
revenge/
├── app/
│   ├── Enums/                  Role, status, dan enum domain (PHP backed enum)
│   ├── Http/
│   │   ├── Controllers/        Public · Auth · User · Provider · Admin
│   │   ├── Middleware/         Auth, role, throttle, activity check
│   │   └── Requests/           Form Request = validasi server per endpoint
│   ├── Models/                 Eloquent model
│   ├── Policies/               Otorisasi per resource (ownership check)
│   ├── Repositories/
│   │   ├── Contracts/          Interface — supaya controller tidak tahu Eloquent
│   │   └── Eloquent/           Implementasi query PostgreSQL
│   ├── Services/               Aturan bisnis: moderasi, bookmark, audit
│   │   └── Moderation/Rules/   Satu file per aturan deteksi spam
│   ├── Support/                TextNormalizer, SlugGenerator, DateHelper
│   ├── Console/Commands/       Scheduled: tandai kedaluwarsa, ingatkan tenggat
│   └── Providers/              Binding repository, peta policy
├── config/moderation.php       Bobot skor + threshold anti-spam (bukan hard-coded)
├── database/
│   ├── migrations/             Skema PostgreSQL, FK, CHECK, index
│   ├── seeders/                Data demo termasuk contoh yang kena tiap aturan
│   └── factories/              Data factory untuk test
├── public/
│   ├── css/  js/  images/      Aset statis (nol build)
├── resources/views/            Blade = HTML, dikelompokkan per area role
├── storage/app/private/uploads/ Bukti organisasi — DI LUAR webroot (Panduan §8)
├── tests/
│   ├── Unit/                   Satu test per aturan moderasi
│   └── Feature/                Alur per role + keamanan (IDOR, CSRF, SQLi, XSS)
└── docs/                       SRS, ERD, diagram, test plan, panduan
```

---

## Alur kerja tim

```
main     ← kode terintegrasi, dilindungi (branch protection + wajib review)
 └─ develop  ← integrasi, selalu bisa dijalankan
     └─ feature/<nama>-<tugas>
```

Aturan minimum:

- Tidak ada edit langsung ke `main` atau `develop`
- Satu commit satu perubahan terkait. Format: `feat(auth): tambah login provider`
- Pull request wajib menyertakan: masalah, ringkasan, cara uji, screenshot bila UI berubah
- Review wajib oleh **IT Manager** sebelum merge
- Tidak ada secret di repo. Kalau bocor, **rotate** dahulu, baru riwayat Git dibersihkan

Rincian: `docs/00-project/`

---

## Pengujian

| Berkas | Yang dijamin |
|---|---|
| `tests/Unit/Moderation/` | Tiap aturan deteksi spam, satu test per aturan |
| `tests/Feature/RoutingTest.php` | 63 rute ada, punya nama, dan middleware-nya menahan |
| `tests/Feature/ViewSmokeTest.php` | Setiap halaman benar-benar dirender untuk tiap role |
| `tests/Feature/WriteFlowTest.php` | Alur tulis lewat HTTP: draf, pengajuan, moderasi, laporan, bookmark |
| `tests/Feature/RuntimeRegressionTest.php` | Cache production, id non-angka, dan rate limiter |
| `tests/Feature/Database/DatabaseSeederTest.php` | Seed jalan dari database kosong dan deterministik |
| `tests/Feature/Database/FactoryTest.php` | Factory cocok dengan skema database |

```bash
php artisan test                        # semuanya
php artisan test --filter=ViewSmokeTest # satu berkas
```

> Catatan: `RuntimeRegressionTest` sengaja memaksa `CACHE_STORE=database`.
> Dengan cache array, test biasa tidak akan pernah menangkap masalah cache.

---

## Graphify (analisis kode)

Knowledge graph proyek dibangun dengan Graphify:

```bash
graphify extract . --code-only     # bangun graph.json dari kode
graphify cluster-only .            # deteksi komunitas + laporan
graphify god-nodes --top 10        # simpul paling terhubung
graphify query "alur moderasi kegiatan" --budget 1200
```

Keluaran ada di `graphify-out/` (graph.json, graph.html, GRAPH_REPORT.md)
dan **tidak** ikut di Git — bisa dibangun ulang kapan saja.

---

## Perintah yang sering dipakai

```bash
php artisan migrate:fresh --seed   # reset total + isi ulang data demo
php artisan migrate:rollback       # mundur 1 batch
php artisan route:list             # lihat semua route
php artisan db:show                # ringkasan isi database
php artisan make:enum StatusEvent  # buat enum baru
php artisan test --filter=Moderation
php artisan test                   # jalankan suite penuh
php artisan config:clear           # Setelah ubah .env
```

---

## Keputusan arsitektur

Dokumen lengkap di `docs/02-design/`. Ringkas:

| Keputusan | Alasan |
|---|---|
| Laravel 13.37.0 | Laravel 11 diblokir Composer karena 5 security advisory aktif (XSS, CRLF injection, File Validation Bypass, Temporary Signed URL confusion). Proyek ini justru dinilai dari bagian keamanan — tidak boleh menjadi demo CVE. |
| PostgreSQL | Diwajibkan Panduan. Dipakai `TIMESTAMPTZ` + `CHECK` constraint agar integritas ditegakkan database, bukan hanya validasi form. |
| Blade (HTML) + Bootstrap CDN | Tanpa build step — anggota bisa langsung buka di browser tanpa `npm install`. |
| Auth manual | `Laravel Breeze` membawa Tailwind + Alpine.js yang bertabrakan dengan pilihan Bootstrap. Auth facade bawaan cukup, dan kontrol penuh atas tampilan. |
| `changes_requested` sebagai status | Menutup ketidaksesuaian antara Panduan §4.1 (admin bisa "minta perbaikan") dan §7.3 (tabel status tidak punya status tersebut). Tercatat di `docs/00-project/change-log.md`. |
| Tanpa auto-publish | Panduan §7.4: jangan auto-publish untuk akun tanpa rekam jejak. Skor moderasi hanya menentukan **prioritas antrean**; keputusan approve/reject selalu oleh admin. |

---

## Tim

| Peran | Tanggung jawab utama |
|---|---|
| IT Manager | Koordinasi, kontrak integrasi, code review, rilis, demo |
| Backend Developer | Autentikasi, otorisasi, CRUD, mesin moderasi, query |
| Frontend Developer | Halaman Blade, responsif, komponen, vanilla JS |
| UI/UX Designer | Alur, prototipe, design tokens, uji usability |
| Dokumentasi & QA | SRS, ERD, diagram, test plan, bukti pengujian, laporan |

Matriks rinci: `docs/00-project/raci-matrix.md`