<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DateHelper;
use App\Support\SlugGenerator;
use App\Support\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Komponen pendukung yang seluruhnya bergantung pada detail kecil.
 *
 * TextNormalizer menentukan apa yang dianggap kata yang sama, jadi kalau
 * normalisasi longgar, aturan moderasi bisa dilewati hanya dengan
 * mengubah e menjadi 3. SlugGenerator menjaga URL tetap unik. DateHelper
 * menentukan waktu yang ditampilkan ke pengguna, dan seluruh aplikasi
 * memakai Asia/Jakarta.
 */
class SupportTest extends TestCase
{
    private TextNormalizer $normaliser;

    private SlugGenerator $slugs;

    private DateHelper $dates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normaliser = new TextNormalizer;
        $this->slugs = new SlugGenerator;
        $this->dates = new DateHelper;
    }

    // ---------------------------------------------------------- normalisasi

    /** @return array<string, array{string, string}> */
    public static function normalisasiProvider(): array
    {
        return [
            'huruf besar tidak berpengaruh' => ['GRATIS', 'gratis'],
            'eyd become 3' => ['gr4t1s', 'gratis'],
            'huruf besar diturunkan' => ['GrAtIs', 'gratis'],
            'spasi berlebih dirapatkan' => ['  lompa  rijek  ', 'lomba rijke'],
            'tanda baca dibuang' => ['gratis, promo!', 'gratis promo'],
            'tanda hubung jadi spasi' => ['lomba-rohani', 'lomba rohani'],
            'tanda hubung angka dipertahankan' => ['seminar-2026', 'seminar 2026'],
            'huruf beraksen diratakan' => ['penerjemahan', 'penerjemahan'],
        ];
    }

    #[DataProvider('normalisasiProvider')]
    public function normalisasi_membuat_variasi_tulis_menyatu(string $masuk, string $harapan): void
    {
        $this->assertSame($harapan, $this->normaliser->normalise($masuk));
    }

    #[Test]
    public function contains_bertahan_terhadap_variasi_tulis(): void
    {
        $this->assertTrue($this->normaliser->contains('Promo GR4T1S Felixons', 'gratis'));
        $this->assertTrue($this->normaliser->contains('Lomba BITS Sequences', 'lomba.bits'));
        $this->assertFalse($this->normaliser->contains('k modestly', 'k moderately'));
    }

    #[Test]
    public function jumlah_kemunculan_fraasa_dihitung_setelah_normalisasi(): void
    {
        $teks = 'Gratis! GR4T1S lagi, dan GRATIS lagi.';

        $this->assertSame(3, $this->normaliser->countPhrase($teks, 'gratis'));
        $this->assertSame(0, $this->normaliser->countPhrase($teks, 'judol'));
    }

    #[Test]
    public function token_membuang_tanda_baca_dan_menyeragamkan(): void
    {
        $this->assertSame(
            ['lomba', 'rjieks', '2026'],
            $this->normaliser->tokens('Lomba, Rjieks!! 2026'),
        );
    }

    /**
     * Pola multi kata hanya dianggap cocok kalau SEMUA kata ada,
     * dengan urutan bebas. Ini yang membuat "gratis" sendirian tidak
     * cukup untuk ditolak, tapi "transfer ke rekening" cukup.
     */
    #[Test]
    public function pola_hanya_cocok_bila_semua_kata_ada(): void
    {
        $teks = 'bayar dulu, lalu transfer ke rekening kami';

        $this->assertTrue($this->normaliser->containsAllWords($teks, ['transfer', 'rekening', 'bayar']));
        $this->assertFalse($this->normaliser->containsAllWords($teks, ['transfer', 'rekening', 'bitcoin']));
        $this->assertTrue($this->normaliser->containsAllWords($teks, ['rekening', 'transfer']));
    }

    // --------------------------------------------------------------- slug

    #[Test]
    public function slug_dari_judul_unicode(): void
    {
        $this->assertSame('lomba-baca-2026', $this->slugs->base('Lomba Baca 2026'));
        $this->assertSame('seminar-kesehatan', $this->slugs->base('Seminar Kesehatan'));
    }

    #[Test]
    public function slug_kosong_dari_judul_tanpa_huruf(): void
    {
        $base = $this->slugs->base('2026 !!!');

        $this->assertNotSame('', $base);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $base);
    }

    /**
     * Daftar slug reservasi diserahkan pemanggilnya, bukan disimpan di
     * kelas: yang berbeda antara tabel events dan tabel lain.
     */
    #[Test]
    public function slug_reservasi_diberikan_oleh_pemanggil(): void
    {
        $this->assertFalse($this->slugs->isReserved('dasbor'));

        $this->assertTrue($this->slugs->isReserved('dasbor', ['dasbor']));
        $this->assertFalse(
            $this->slugs->isReserved('dasbor', ['admin', 'keluar']),
            'Daftar reservasi harus dibandingkan persis, bukan dengan awalan.',
        );
    }

    // ---------------------------------------------------------------- tanggal

    /**
     * Zona waktu tampilan adalah Asia/Jakarta, bukan zona waktu server.
     *
     * Kemungkinan salah di sini tinggi: server sering berjalan di UTC,
     * sedangkan semua orang di kampus melihat waktu WIB. Kalau kelas ini
     * ikut zona server, jadwal kegiatan bergeser tujuh jam.
     */
    #[Test]
    public function tanggal_ditampilkan_dalam_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));

        // 2026-01-01 00:30 UTC = 07.30 WIB tanggal yang sama.
        $momen = new \DateTimeImmutable('2026-01-01 00:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('1 Jan 2026, 07.30', $this->dates->dateTime($momen));
        $this->assertSame('1 Januari 2026', $this->dates->date($momen));
    }

    #[Test]
    public function tanggal_dalam_bahasa_indonesia(): void
    {
        // Bulan yang berbeda antara Inggris dan Indonesia dipakai sebagai
        // penanda: Maret/March, Mei/May, Agustus/August.
        $this->assertSame('1 Maret 2026', $this->dates->date(new \DateTimeImmutable('2026-03-01 00:00:00', new \DateTimeZone('Asia/Jakarta'))));
        $this->assertSame('1 Mei 2026', $this->dates->date(new \DateTimeImmutable('2026-05-01 00:00:00', new \DateTimeZone('Asia/Jakarta'))));
        $this->assertSame('1 Agustus 2026', $this->dates->date(new \DateTimeImmutable('2026-08-01 00:00:00', new \DateTimeZone('Asia/Jakarta'))));

        $this->assertStringStartsWith(
            'Kamis, ',
            $this->dates->longDate(new \DateTimeImmutable('2026-11-12 09:00:00', new \DateTimeZone('Asia/Jakarta'))),
        );
    }

    /**
     * Nilai untuk <input type="datetime-local"> dan <input type="date">
     * punya format berbeda, dan keduanya harus menggambarkan momen yang
     * sama setelah dibaca browser.
     */
    #[Test]
    public function nilai_form_sesuai_jenis_inputnya(): void
    {
        $momen = new \DateTimeImmutable('2026-03-09 14:05:00', new \DateTimeZone('Asia/Jakarta'));

        $untukDateTime = $this->dates->inputValue($momen);
        $untukTanggal = $this->dates->machineValue($momen);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $untukDateTime);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $untukTanggal);

        $this->assertSame(
            $momen->format('Y-m-d'),
            (new \DateTimeImmutable($untukDateTime))->format('Y-m-d'),
            'Nilai datetime-local harus menunjuk hari yang sama.',
        );
        $this->assertSame($untukTanggal, substr($untukDateTime, 0, 10));
    }

    #[Test]
    public function rentang_waktu_membaca_ringkas_bila_satu_hari(): void
    {
        $mulai = new \DateTimeImmutable('2026-05-10 09:00:00', new \DateTimeZone('Asia/Jakarta'));
        $sehari = new \DateTimeImmutable('2026-05-10 14:30:00', new \DateTimeZone('Asia/Jakarta'));
        $berbeda = new \DateTimeImmutable('2026-05-11 14:30:00', new \DateTimeZone('Asia/Jakarta'));

        $singkat = $this->dates->range($mulai, $sehari);
        $this->assertSame(
            '10 Mei 2026, 09.00-14.30',
            $singkat,
            'Jam selesai tidak boleh hilang untuk acara satu hari.',
        );

        $panjang = $this->dates->range($mulai, $berbeda);
        $this->assertSame('10-11 Mei 2026', $panjang);
    }

    #[Test]
    public function jarak_waktu_dibaca_dalam_hari(): void
    {
        $this->assertSame('hari ini', $this->dates->relative(now()));
        $this->assertSame('besok', $this->dates->relative(now()->addDay()));
        $this->assertSame('kemarin', $this->dates->relative(now()->subDay()));
        $this->assertSame('5 hari lagi', $this->dates->relative(now()->addDays(5)));
        $this->assertSame('3 hari lalu', $this->dates->relative(now()->subDays(3)));
    }
}
