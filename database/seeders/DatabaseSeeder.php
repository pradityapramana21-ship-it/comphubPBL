<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EventMode;
use App\Enums\EventStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventReport;
use App\Models\ModerationLog;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\EventService;
use App\Services\ModerationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data demo nonproduksi.
 *
 * Tiga hal yang dijaga seeder ini:
 *
 *  1. DETERMINISTIK. Tidak ada faker di sini. Data demo harus sama
 *     setiap kali dijalankan, karena anggota lain memverifikasi lewat
 *     nomor yang tertera di README, dan tangkapan layar Presentasi
 *     menunjuk ke baris yang persis sama.
 *
 *  2. MENCAKAI SEMUA STATUS. Demo harus bisa menunjukkan antrean yang
 *     belum bersih, kegiatan yang ditolak, dan laporan yang terbuka.
 *     Seed yang semuanya rapi tidak pernah bisa dipakai untuk
 *     menjelaskan sistem moderasi.
 *
 *  3. TIDAK ADA RAHASIA. Satu kata sandi untuk semua akun, dan
 *     semuanya tertulis di README. Jalankan hanya di database
 *     pengembangan atau test.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $admin = $this->seedAccounts();

        $categories = $this->seedCategories();

        $profiles = $this->seedProviders($admin);

        $this->seedEvents($profiles, $categories);

        $this->seedBookmarksAndReports();
    }

    /**
     * Akun demo: dua admin, tiga penyedia, empat mahasiswa.
     */
    private function seedAccounts(): User
    {
        $rows = [
            ['Admin Jurusan', 'admin@lomba.test', UserRole::Admin],
            ['Admin Fakultas', 'admin2@lomba.test', UserRole::Admin],
            ['BEM Universitas', 'bem@lomba.test', UserRole::Provider],
            ['Himpunan Teknik', 'himpunan@lomba.test', UserRole::Provider],
            ['Salsa creativity', 'salsa@lomba.test', UserRole::Provider],
            ['Andi', 'andi@lomba.test', UserRole::User],
            ['Budi', 'budi@lomba.test', UserRole::User],
            ['Citra', 'citra@lomba.test', UserRole::User],
        ];

        foreach ($rows as [$name, $email, $role]) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => $role->value,
            ]);
        }

        // Satu akun yang ditangguhkan, supaya halaman pengguna milik
        // admin punya isi selain akun yang semuanya aktif.
        User::create([
            'name' => 'Dina',
            'email' => 'dina@lomba.test',
            'password' => Hash::make(self::DEMO_PASSWORD),
            'role' => UserRole::User->value,
        ])->forceFill([
            'account_status' => 'suspended',
            'suspended_at' => now(),
            'suspension_note' => 'Akun contoh tidak boleh dipakai untuk pengumuman resmi.',
        ])->save();

        $admin = User::where('email', 'admin@lomba.test')->firstOrFail();

        AuditLog::create([
            'actor_user_id' => $admin->id,
            'actor_role' => $admin->role->value,
            'action' => 'user.suspended',
            'target_type' => User::class,
            'target_id' => User::where('email', 'dina@lomba.test')->value('id'),
            'metadata' => ['catatan' => 'akun contoh'],
            'ip_address' => '127.0.0.1',
        ]);

        return $admin;
    }

    /**
     * Kategori sesuai taksonomi yang dipakai seluruh halaman.
     *
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $rows = [
            'Lomba' => 'Lomba akademik, seni, dan kreativitas mahasiswa.',
            'Webinar' => 'Pertemuan daring yang bisa diikuti dari mana saja.',
            'Seminar' => 'Seminar bertema, umumnya berlangsung di kampus.',
            'Workshop' => 'Pelatihan praktis dengan kuota peserta terbatas.',
        ];

        $categories = [];

        foreach ($rows as $name => $description) {
            $categories[$name] = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $description,
                'is_active' => true,
            ]);
        }

        return $categories;
    }

    /**
     * Tiga kondisi verifikasi: diterima, menunggu, dan ditolak.
     * Ketiganya perlu ada supaya antrean admin punya isi.
     *
     * @return array{verified: ProviderProfile, waiting: ProviderProfile, rejected: ProviderProfile}
     */
    private function seedProviders(User $admin): array
    {
        $bem = $this->makeProfile(
            'bem@lomba.test',
            'Badan Eksekutif Mahasiswa Universitas Demo',
            'Universitas Demo',
            'Lembaga mahasiswa yang menaungi Ketua BEM Universitas Demo.',
        );
        $bem->markVerified($admin);

        $himpunan = $this->makeProfile(
            'himpunan@lomba.test',
            'Himpunan Teknik Elektro Demo',
            'Institut Teknologi Demo',
            'Unit mahasiswa yang menjalankan kegiatan teknis setiap semester.',
        );

        $salsa = $this->makeProfile(
            'salsa@lomba.test',
            'Unit Seni Tari Salsa Demo',
            'Universitas Demo',
            'Unit kegiatan tari yang tampil di setiap agenda mahasiswa.',
        );
        $salsa->markRejected($admin, 'Nama organisasi belum bisa dipastikan. Mohon lampirkan bukti dari unit kegiatan.',);

        return ['verified' => $bem, 'waiting' => $himpunan, 'rejected' => $salsa];
    }

    private function makeProfile(string $email, string $organization, string $campus, string $description): ProviderProfile
    {
        return ProviderProfile::create([
            'user_id' => User::where('email', $email)->value('id'),
            'organization_name' => $organization,
            'campus_name' => $campus,
            'official_email' => $email,
            'website_url' => 'https://'.Str::limit(Str::slug($organization), 40, '').'.lomba.test',
            'description' => $description,
        ]);
    }

    /**
     * Kegiatan di semua status, termasuk contoh yang sengaja bermasalah.
     *
     * @param  array{verified: ProviderProfile, waiting: ProviderProfile, rejected: ProviderProfile}  $profiles
     * @param  array<string, Category>  $categories
     */
    private function seedEvents(array $profiles, array $categories): void
    {
        $verified = $profiles['verified'];

        $published = [
            [
                'title' => 'Lomba Penulisan Esai Nasional 2026',
                'category' => 'Lomba',
                'description' => 'Lomba esai terbuka untuk mahasiswa tingkat undergraduate dari '
                    .'berbagai kampus. Tulis esai sepanjang 3.000 kata bertema "Kampus yang Kita '
                    .'Bangun". Peserta mendaftarkan satu judul dan mengunggah berkasbefore '
                    .'deadline. Hasil diumumkan lewat website resmi penyelenggara.',
                'deadline' => now()->addDays(9),
                'start' => now()->addDays(24),
            ],
            [
                'title' => 'Webinar Teknologi AI untuk Dosen',
                'category' => 'Webinar',
                'description' => 'Webinar pengenalan tools AI untuk dosen dan mahasiswa. Pembicara dari '
                    .'komunitas praktisi. Materi direkam dan dibagikan ke peserta yang terdaftar. '
                    .'Absensi dibuka lewat Zoom dan peserta tidak dipungut biaya.',
                'deadline' => now()->addDays(3),
                'start' => now()->addDays(10),
            ],
            [
                'title' => 'Seminar Kesehatan Tidur bagi Mahasiswa',
                'category' => 'Seminar',
                'description' => 'Seminar kesehatan organizer dengan akademisi. Terbuka untuk umum dan '
                    .'mahasiswa, tidak dipungut biaya. Ikuti langkah pendaftaran pada formulir resmi '
                    .'dan datangkan lima belas menit lebih awal.',
                'deadline' => now()->addDays(16),
                'start' => now()->addDays(30),
            ],
            [
                'title' => 'Workshop Menulis CV dan Surat Lamaran',
                'category' => 'Workshop',
                'description' => 'Workshop praktis menulis CV yang ringkas dan jujur. Peserta membawa '
                    .'laptop dan portofolio masing-masing. Kuota 40 orang, pendaftaran '
                    .'ditutup saat kuota penuh.',
                'deadline' => now()->addDay(),
                'start' => now()->addDays(7),
            ],
        ];

        foreach ($published as $index => $row) {
            $slug = Str::slug($row['title']);

            $event = Event::create([
                'provider_profile_id' => $verified->id,
                'category_id' => $categories[$row['category']]->id,
                'title' => $row['title'],
                'slug' => $slug,
                'description' => $row['description'],
                'requirements' => 'Membawa kartu mahasiswa dan mengisi formulir pendaftaran.',
                'registration_url' => 'https://daftar.lomba.test/'.$slug,
                'mode' => EventMode::Online->value,
                'platform_url' => 'https://meet.lomba.test/'.$slug,
                'event_start_at' => $row['start'],
                'event_end_at' => $row['start']->copy()->addDay(),
                'registration_deadline' => $row['deadline'],
                'status' => EventStatus::Approved->value,
                'submitted_at' => now()->subDays(10),
                'published_at' => now()->subDays(9),
            ]);

            // Jejak pengajuan diberi tanggal jauh di belakang. Kalau semua
            // entri dibuat pada waktu seed,ModerationService akan menghitung
            // semuanya sebagai "5 pengajuan dalam 1 jam" dan seluruh data
            // demo akan tampak mencurigakan begitu saja.
            ModerationLog::create([
                'event_id' => $event->id,
                'actor_user_id' => null,
                'action' => 'submit',
                'reason' => 'Diajukan oleh penyedia.',
                'risk_score' => 0,
            ])->forceFill(['created_at' => now()->subDays(20 + $index)])->save();

            AuditLog::create([
                'actor_user_id' => null,
                'actor_role' => null,
                'action' => 'event.approved',
                'target_type' => Event::class,
                'target_id' => $event->id,
                'metadata' => ['sumber' => 'seed'],
            ]);
        }

        // Menunggu keputusan admin.
        $pending = Event::create([
            'provider_profile_id' => $verified->id,
            'category_id' => $categories['Lomba']->id,
            'title' => 'Lomba Business Plan Untuk Mahasiswa',
            'slug' => 'lomba-business-plan',
            'description' => 'Lomba business plan untuk mahasiswa. Peserta menyusun rencana usaha '
                .'lengkap dan mempresentasikannya di depan juri. Penilaian dilakukan oleh praktisi '
                .'yang pernah menjalankan usaha skala kecil.',
            'requirements' => 'Rencana usaha maksimal 20 halaman.',
            'registration_url' => 'https://daftar.lomba.test/business-plan',
            'mode' => EventMode::Offline->value,
            'location_text' => 'Gedung Rektorat, Universitas Demo',
            'event_start_at' => now()->addDays(45),
            'event_end_at' => now()->addDays(46),
            'registration_deadline' => now()->addDays(20),
            'status' => EventStatus::Pending->value,
            'submitted_at' => now()->subDay(),
        ]);

        ModerationLog::create([
            'event_id' => $pending->id,
            'actor_user_id' => null,
            'action' => 'submit',
            'reason' => 'Diajukan oleh penyedia.',
            'risk_score' => 0,
        ])->forceFill(['created_at' => now()->subHours(6)])->save();

        // Pengajuan kedua yang benar-benar ditandai: isinya masih tipis.
        //
        // Milik organisasi yang BELUM terverifikasi, karena aturan
        // kualitas isi sengaja dilewati untuk penyedia tepercaya — slender
        // penjelasan dari BEM yang sudah diperiksa tidak boleh dianggap tipis.
        // Skornya dihitung ulang di bawah supaya kolom risk_score tidak
        // pernah berbeda dari apa yang ModerationService hasilkan.
        $thin = Event::create([
            'provider_profile_id' => $profiles['waiting']->id,
            'category_id' => $categories['Lomba']->id,
            'title' => 'Bursa Kerja',
            'slug' => 'bursa-kerja',
            'description' => 'Bursa kerja untuk mahasiswa.',
            'registration_url' => 'https://daftar.lomba.test/bursa-kerja',
            'mode' => EventMode::Offline->value,
            'location_text' => 'Gedung Student Center, Universitas Demo',
            'event_start_at' => now()->addDays(35),
            'event_end_at' => now()->addDays(36),
            'registration_deadline' => now()->addDays(18),
            'status' => EventStatus::Pending->value,
            'submitted_at' => now()->subHours(10),
        ]);

        $thinVerdict = app(ModerationService::class)->inspect(app(EventService::class)->contextFor($thin));

        $thin->forceFill(['risk_score' => $thinVerdict->totalScore])->save();

        ModerationLog::create([
            'event_id' => $thin->id,
            'actor_user_id' => null,
            'action' => 'auto_flagged',
            'rule_key' => 'content_quality',
            'reason' => $thinVerdict->summary(),
            'risk_score' => $thinVerdict->totalScore,
        ]);

        // Ditolak otomatis karena tanda-tanda spam yang jelas.
        $spam = Event::create([
            'provider_profile_id' => $profiles['waiting']->id,
            'category_id' => $categories['Lomba']->id,
            'title' => 'Lomba Terbuka untuk Semua',
            'slug' => 'lomba-terbuka-semua',
            'description' => 'Lomba terbuka untuk semua. Bayar sebelum daftar, lalu '
                .'transfer ke rekening yang kami kirim lewat wa. Pendaftaran gratis, cek link! '
                .'Daftar di link! Bayar sebelum daftar! Cheap! Daftar di link! Cheap! Daftar di link! '
                .'Cheap! Daftar di link! Cheap! Daftar di link! Cheap! Daftar di link!',
            'registration_url' => 'https://bit.ly/daftarsekil',
            'mode' => EventMode::Online->value,
            'platform_url' => 'https://bit.ly/join',
            'event_start_at' => now()->addDays(5),
            'event_end_at' => now()->addDays(6),
            'registration_deadline' => now()->addDays(3),
            'status' => EventStatus::Rejected->value,
            'risk_score' => 100,
            'submitted_at' => now()->subHours(6),
            'rejection_reason' => 'Permintaan transfer sebelum pendaftaran dan tautan pemendek menunjukkan pola spam.',
        ]);

        ModerationLog::create([
            'event_id' => $spam->id,
            'actor_user_id' => null,
            'action' => 'auto_rejected',
            'rule_key' => 'keyword_blacklist',
            'reason' => 'Pola penanda ditemukan: bayar sebelum daftar.',
            'risk_score' => 70,
        ]);

        ModerationLog::create([
            'event_id' => $spam->id,
            'actor_user_id' => null,
            'action' => 'auto_rejected',
            'rule_key' => 'suspicious_url',
            'reason' => 'Tautan memakai domain pemendek yang sering dipakai spam.',
            'risk_score' => 25,
        ]);

        // Diminta diperbaiki, supaya penyedia punya catatan untuk dibaca.
        Event::create([
            'provider_profile_id' => $verified->id,
            'category_id' => $categories['Webinar']->id,
            'title' => 'Webinar Git Untuk Mahasiswa',
            'slug' => 'webinar-git-untuk-mahasiswa',
            'description' => 'Webinar pengenalan Git dan GitHub untuk mahasiswa yang baru mulai '
                .'bekerja sama dalam tim. Pembahasan alur kerja dasar, konflik merge, dan kebiasaan '
                .'menulis pesan commit yang bisa dibaca.',
            'registration_url' => 'https://daftar.lomba.test/webinar-git',
            'mode' => EventMode::Online->value,
            'platform_url' => 'https://meet.lomba.test/webinar-git',
            'event_start_at' => now()->addDays(20),
            'event_end_at' => now()->addDays(20),
            'registration_deadline' => now()->addDays(12),
            'status' => EventStatus::ChangesRequested->value,
            'submitted_at' => now()->subDays(3),
            'rejection_reason' => 'Mohon tambahkan jadwal sesi kedua dan aturan absensi.',
        ]);

        // Draf: status satu-satunya yang boleh disunting penyedia.
        Event::create([
            'provider_profile_id' => $verified->id,
            'category_id' => $categories['Seminar']->id,
            'title' => 'Seminar Keuangan Pribadi Untuk Mahasiswa',
            'slug' => 'seminar-keuangan-pribadi',
            'description' => 'Seminar pengelolaan keuangan pribadi untuk mahasiswa. Bahasan meliputi '
                .'menabung, utang, dan menyusun anggaran bulanan. Peserta boleh bertanya langsung.',
            'registration_url' => 'https://daftar.lomba.test/seminar-keuangan',
            'mode' => EventMode::Hybrid->value,
            'location_text' => 'Ruang Serbaguna, Universitas Demo',
            'platform_url' => 'https://meet.lomba.test/seminar-keuangan',
            'event_start_at' => now()->addDays(60),
            'event_end_at' => now()->addDays(60),
            'registration_deadline' => now()->addDays(40),
            'status' => EventStatus::Draft->value,
        ]);

        // Arsip: kegiatan lama yang ditutup, bukan dihapus.
        Event::create([
            'provider_profile_id' => $verified->id,
            'category_id' => $categories['Lomba']->id,
            'title' => 'Lomba Debat Bahasa Inggris',
            'slug' => 'lomba-debat-bahasa-inggris',
            'description' => 'Lomba debat bahasa Inggris untuk mahasiswa. Tahapan penyisihan '
                .'dilakukan daring dan final dilaksanakan di ruang aula utama.',
            'registration_url' => 'https://daftar.lomba.test/debat',
            'mode' => EventMode::Offline->value,
            'location_text' => 'Aula Barat, Universitas Demo',
            'event_start_at' => now()->subDays(20),
            'event_end_at' => now()->subDays(19),
            'registration_deadline' => now()->subDays(40),
            'status' => EventStatus::Archived->value,
            'submitted_at' => now()->subDays(60),
            'published_at' => now()->subDays(55),
            'archived_at' => now()->subDays(5),
        ]);

        // Kedaluwarsa: tayang dan ditutup karena lewat tenggat. Statusnya
        // expired, bukan archived, supaya perbedaan keduanya kelihatan.
        Event::create([
            'provider_profile_id' => $verified->id,
            'category_id' => $categories['Webinar']->id,
            'title' => 'Webinar Rekrutmen Jarak Jauh',
            'slug' => 'webinar-async-hiring',
            'description' => 'Webinar tentang proses rekrutmen jarak jauh. Pembicara '
                .'berpengalaman dari perusahaan teknologi. Rekaman dibagikan kepada peserta.',
            'registration_url' => 'https://daftar.lomba.test/async-hiring',
            'mode' => EventMode::Online->value,
            'platform_url' => 'https://meet.lomba.test/async-hiring',
            'event_start_at' => now()->subDays(3),
            'event_end_at' => now()->subDays(2),
            'registration_deadline' => now()->subDays(8),
            'status' => EventStatus::Expired->value,
            'submitted_at' => now()->subDays(40),
            'published_at' => now()->subDays(35),
        ]);

        // Draf milik penyedia yang belum diverifikasi: tetap bisa dibuka
        // pemiliknya, tapi tidak boleh masuk antrean admin.
        Event::create([
            'provider_profile_id' => $profiles['waiting']->id,
            'category_id' => $categories['Workshop']->id,
            'title' => 'Workshop Merakit PC',
            'slug' => 'workshop-merakit-pc',
            'description' => 'Workshop merakit PC dari pemilihan komponen sampai sistem terpasang. '
                .'Peserta membawa komponen sendiri dan mengikuti panduanavailable dari teknisi.',
            'registration_url' => 'https://daftar.lomba.test/merakit-pc',
            'mode' => EventMode::Offline->value,
            'location_text' => 'Lab Komputasi, Institut Teknologi Demo',
            'event_start_at' => now()->addDays(40),
            'event_end_at' => now()->addDays(40),
            'registration_deadline' => now()->addDays(25),
            'status' => EventStatus::Draft->value,
        ]);
    }

    /**
     * Bookmark dan laporan, termasuk laporan yang sudah ditangani.
     */
    private function seedBookmarksAndReports(): void
    {
        $published = Event::where('status', EventStatus::Approved->value)->orderBy('id')->get();
        $andi = User::where('email', 'andi@lomba.test')->firstOrFail();
        $budi = User::where('email', 'budi@lomba.test')->firstOrFail();

        foreach ($published->take(3) as $index => $event) {
            // Bookmark memakai primary key komposit dan sengaja tidak
            // punya kolom id, jadi ditulis lewat query builder.
            DB::table('bookmarks')->insert([
                'user_id' => $index === 0 ? $budi->id : $andi->id,
                'event_id' => $event->id,
                'created_at' => now(),
            ]);

            DB::table('events')->where('id', $event->id)->increment('bookmark_count');
        }

        $target = $published->first();

        if ($target !== null) {
            EventReport::create([
                'event_id' => $target->id,
                'reporter_user_id' => $budi->id,
                'reason_code' => ReportReason::BrokenLink->value,
                'description' => 'Tautan pendaftaran berhenti di halaman kosong sejak kemarin.',
                'status' => ReportStatus::Open->value,
            ]);
        }
    }
}