<?php

declare(strict_types=1);

/**
 * Konfigurasi moderasi otomatis.
 *
 * Semua angka di sini, bukan ditulis mati di dalam kelas aturan, supaya
 * bisa dituning setelah menguji data sintetis tanpa menyentuh kode
 * (Panduan §7.4).
 *
 * PENTING: apa pun nilainya, skor tidak pernah menyetujui kegiatan secara
 * otomatis. Skor hanya menentukan seberapa mendesak sebuah pengajuan
 * dilihat admin (Panduan §7.4: jangan auto-publish).
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Ambang keputusan
    |--------------------------------------------------------------------------
    |
    | reject : skor ini atau lebih tinggi menolak pengajuan secara otomatis
    |          karena melanggar aturan eksplisit. Admin tetap melihat dan
    |          penyedia tetap bisa memperbaiki lalu mengajukan ulang.
    |
    | flag   : skor ini atau lebih tinggi memunculkan pengajuan di urutan
    |          teratas antrean admin, dengan alasan yang wajib dibaca.
    |
    | Di bawah flag, pengajuan tetap masuk antrean normal. Tidak ada jalur
    | yang menampilkan kegiatan ke publik tanpa keputusan admin.
    |
    */

    'thresholds' => [
        'reject' => env('MODERATION_REJECT_THRESHOLD', 60),
        'flag' => env('MODERATION_FLAG_THRESHOLD', 25),
        'cap' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bobot tiap aturan
    |--------------------------------------------------------------------------
    |
    | Angka dipilih supaya beberapa tanda lemah yang menumpuk tidak langsung
    | menolak, sedangkan satu pelanggaran keras langsung melewati ambang.
    |
    | keyword_blacklist = 100 : lewat ambang reject (60) dengan sendirinya
    | rate_limit        =  40 : butuh digabung satu aturan lain
    | duplicate         =  30
    | suspicious_url    =  25
    | contact_leak      =  20
    | content_quality   =  10 : sinyal lemah, hampir tidak pernah menolak
    |
    */

    'weights' => [
        'keyword_blacklist' => 100,
        'duplicate' => 30,
        'rate_limit' => 40,
        'suspicious_url' => 25,
        'contact_leak' => 20,
        'date_sanity' => 30,
        'content_quality' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kata terlarang
    |--------------------------------------------------------------------------
    |
    | Dicocokkan pada teks yang sudah dinormalisasi (TextNormalizer), jadi
    | "gr4t1s", "Gr4at1ss", dan "G R A T I S" ikut tertangkap.
    |
    | Aturan ini menyaring, bukan menghakimi. "Gratis" sah untuk kegiatan
    | mahasiswa, tapi digabung dengan "transfer", "rekening", "kirim",
    | "sekarang" menjadi pola yang mencurigakan. Karena itu daftar kedua
    | memakai kata, bukan potongan string: semua kata dalam satu pola harus
    | muncul, dan urutannya tidak penting.
    |
    */

    'keywords' => [
        'blocked' => [
            'judol', 'togel', 'slot gacor', 'slot online', 'casino',
            'baccarat', 'poker online', 'togel online', 'pinjol',
            'legalisir ijazah', 'anak hilang', 'cicil hey',
        ],

        'patterns' => [
            'minta transfer' => ['transfer', 'rekening', 'bayar'],
            'kirim sekarang' => ['kirim', 'sekarang', 'wa'],
            'bayar sebelum daftar' => ['bayar', 'transfer', 'sebelum', 'daftar'],
            'nomor whatsapp' => ['nomor', 'wa'],
            'kontak langsung' => ['kontak', 'langsung', 'hp'],
            'pinjol cepat' => ['pinjol', 'cepat', 'cairkan'],
            'transfer manual' => ['transfer', 'manual', 'rekening'],
        ],

        // Pola dengan satu kata terlalu longgar dan hanya menambah noise.
        'min_pattern_words' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kualitas isi
    |--------------------------------------------------------------------------
    |
    | Bukan tuduhan, hanya sinyal "konten ini belum cukup jelas untuk
    | ditayangkan". Kegiatan yang sah tetap bisa tertahan di sini kalau
    | deskripsinya cuma satu kalimat.
    |
    */

    'content_quality' => [
        'min_title_words' => 5,
        'min_title_chars' => 15,
        'min_description_chars' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Duplikasi
    |--------------------------------------------------------------------------
    |
    | Judul mirip tidak otomatis berarti spam: organizer boleh mengulang
    | competitions yang sama untuk beberapa wave. Karena itu duplikat hanya
    | menambah skor, dan hanya dianggap kandidat bila penyedia, kemiripan
    | judul, DAN jarak tanggal cocok sekaligus (Panduan §7.2).
    |
    */

    'duplicate' => [
        'title_similarity_threshold' => 0.85,
        'date_window_days' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pembatasan laju
    |--------------------------------------------------------------------------
    |
    | Dua jendela: menahan burst pendek, dan menahan volume harian.
    | Nilai longgar untuk pemakaian nyata.
    |
    */

    'rate_limit' => [
        'per_hour' => 5,
        'per_day' => 20,
        'cooldown_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tautan mencurigakan
    |--------------------------------------------------------------------------
    |
    | Domain yang sah tidak boleh diblokir hanya karena belum dikenal
    | (Panduan §7.2). Yang dicurigai hanya pola khas penipuan: pemendek
    | tautan, situs tempel, berkas executable, dan tautan tanpa HTTPS.
    |
    */

    'suspicious_url' => [
        'shorteners' => [
            'bit.ly', 'tinyurl.com', 's.id', 'cutt.ly', 'ow.ly', 't.co',
            'rebrand.ly', 'shorturl.at', 'is.gd', 'v.gd', 'rb.gy',
        ],

        'paste_sites' => [
            'pastebin.com', 'paste.ee', 'hastebin.com', 'ghostbin.com',
            'dpaste.org', 'controlc.com',
        ],

        'file_extensions' => ['exe', 'bat', 'cmd', 'scr', 'vbs', 'jar', 'apk', 'msi'],

        'require_https' => true,
        'max_links' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kebocoran kontak
    |--------------------------------------------------------------------------
    |
    | Platform menyediakan tombol pendaftaran; menyalin nomor WhatsApp ke
    | deskripsi membuat admin kehilangan kendali dan pengguna tidak punya
    | tempat melapor.
    |
    */

    'contact_leak' => [
        'patterns' => [
            'whatsapp' => '/(?:\+?62|0)8[0-9]{8,11}/u',
            'telegram' => '/(?:t\.me|telegram\.me|telegram\.org\/s\/)/iu',
            'phone_generic' => '/\b(?:wa|hp|telepon|sel)\s*[:.]?\s*(?:\+?62|0)[0-9]{8,12}\b/iu',
            'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu',
        ],

        // Field registration_url boleh memuat email resmi, jadi pemeriksaan
        // email tidak dijalankan terhadap URL pendaftaran.
        'check_registration_url_for_email' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logika tanggal
    |--------------------------------------------------------------------------
    |
    | Sebagian sudah ditegakkan database lewat CHECK constraint. Yang
    | ditambahkan di sini adalah hal yang tidak bisa ditulis sebagai CHECK:
    | tenggat yang hanya beberapa jam sebelum acara, kegiatan yang sudah
    | lewat saat pertama kali diajukan, dan durasi yang tidak masuk akal.
    |
    */

    'date_sanity' => [
        'min_lead_hours' => 2,
        'max_duration_days' => 30,
        'allow_past_events' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kepercayaan penyedia
    |--------------------------------------------------------------------------
    |
    | Menyentuh satu hal saja: pemeriksaan kualitas isi dilonggarkan untuk
    | penyedia terverifikasi yang riwayat pengajuannya bersih.
    |
    | Tidak pernah dilonggarkan untuk keyword_blacklist, suspicious_url,
    | contact_leak, atau date_sanity. Aturan mutlak tidak dinegosiasikan
    | dengan reputasi. Inilah batas yang menjaga agar skor tinggi tidak
    | berarti pembiaran.
    |
    */

    'trust' => [
        'verified_provider_skips' => ['content_quality'],
        'min_clean_submissions' => 3,
        'clean_window_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alur keputusan
    |--------------------------------------------------------------------------
    |
    | auto_publish sengaja bernilai false dan tidak punya kunci 'approve'.
    | Menyetujui kegiatan adalah keputusan manusia, bukan hasil perhitungan.
    |
    */

    'decisions' => [
        'auto_publish' => false,
    ],
];