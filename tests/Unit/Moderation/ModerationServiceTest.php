<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Enums\EventStatus;
use App\Services\ModerationService;
use PHPUnit\Framework\Attributes\Test;

class ModerationServiceTest extends ModerationRuleTestCase
{
    private function service(): ModerationService
    {
        return $this->app->make(ModerationService::class);
    }

    #[Test]
    public function ketujuh_aturan_terdaftar(): void
    {
        $keys = $this->service()->registeredRuleKeys();

        foreach ([
            'keyword_blacklist', 'content_quality', 'duplicate',
            'rate_limit', 'suspicious_url', 'contact_leak', 'date_sanity',
        ] as $expected) {
            $this->assertContains($expected, $keys, "Aturan {$expected} tidak terdaftar.");
        }
    }

    #[Test]
    public function setiap_aturan_punya_bobot_dan_ambang(): void
    {
        $weights = config('moderation.weights');

        foreach ($this->service()->registeredRuleKeys() as $key) {
            $this->assertArrayHasKey($key, $weights, "Aturan {$key} tidak punya bobot di config.");
            $this->assertGreaterThan(0, $weights[$key]);
        }
    }

    #[Test]
    public function pengajuan_bersih_masuk_antrean_bukan_diterbitkan(): void
    {
        $verdict = $this->service()->inspect($this->context());

        $this->assertSame(0, $verdict->totalScore);
        $this->assertFalse($verdict->autoRejected);
        $this->assertFalse($verdict->flagged);

        // Ini yang paling penting: skor nol TETAP pending, tidak approved.
        $this->assertSame(EventStatus::Pending, $verdict->resultingStatus());
    }

    #[Test]
    public function tidak_ada_satu_pun_jalur_yang_menghasilkan_approved(): void
    {
        // Uji setiap status yang mungkin dihasilkan verdict. Tidak ada satu
        // pun yang boleh Approved — inilah embodiment dari Panduan §7.4.
        $contexts = [
            'bersih' => $this->context(),
            'kata terlarang' => $this->context(['description' => 'Lomba judol']),
            'kontak bocor' => $this->context(['description' => 'Hubungi 081234567890']),
            'tautan mencurigakan' => $this->context(['registrationUrl' => 'https://bit.ly/x']),
            'konten tipis' => $this->context(['title' => 'Lomba', 'description' => 'kontak aja']),
        ];

        foreach ($contexts as $label => $context) {
            $status = $this->service()->inspect($context)->resultingStatus();

            $this->assertContains(
                $status,
                [EventStatus::Pending, EventStatus::Rejected],
                "Konteks \"{$label}\" menghasilkan status terlarang: {$status->value}",
            );
            $this->assertNotSame(EventStatus::Approved, $status);
        }
    }

    #[Test]
    public function konfigurasi_tidak_punya_kunci_approve(): void
    {
        // Pengaman kedua: kalau ada yang menambahkan 'approve' ke config
        // dengan sengaja, kelas ini tetap tidak akan memakainya.
        $this->assertArrayNotHasKey('approve', config('moderation.decisions'));
        $this->assertFalse(config('moderation.decisions.auto_publish'));
    }

    #[Test]
    public function skor_kecil_masih_pending_tanpa_penanda(): void
    {
        $verdict = $this->service()->inspect($this->context(['title' => 'Lomba', 'description' => str_repeat('Pendek. ', 20)]));

        $this->assertFalse($verdict->autoRejected);
        $this->assertFalse($verdict->flagged, 'Skor di bawah ambang tidak boleh jadi prioritas.');
        $this->assertSame(EventStatus::Pending, $verdict->resultingStatus());
        $this->assertNotSame([], $verdict->results());
    }

    #[Test]
    public function skor_menengah_jadi_prioritas(): void
    {
        // content_quality (10) + contact_leak (20) = 30, di atas ambang 25.
        $verdict = $this->service()->inspect($this->context([
            'title' => 'Lomba',
            'description' => 'Hubungi 081234567890 untuk info lebih lanjut.',
        ]));

        $this->assertGreaterThanOrEqual((int) config('moderation.thresholds.flag'), $verdict->totalScore);
        $this->assertTrue($verdict->flagged);
        $this->assertFalse($verdict->autoRejected);
        $this->assertSame(EventStatus::Pending, $verdict->resultingStatus());
    }

    #[Test]
    public function skor_tinggi_ditolak_otomatis(): void
    {
        $verdict = $this->service()->inspect($this->context(['description' => 'Lomba judol dan deposit']));

        $this->assertTrue($verdict->autoRejected);
        $this->assertSame(EventStatus::Rejected, $verdict->resultingStatus());
        $this->assertLessThanOrEqual((int) config('moderation.thresholds.cap'), $verdict->totalScore);
    }

    #[Test]
    public function skor_selalu_dijepit_di_batas_atas(): void
    {
        $verdict = $this->service()->inspect($this->context([
            'title' => 'Lomba',
            'description' => 'judol dan hubungi 081234567890 daftar di https://bit.ly/x',
            'registrationUrl' => 'https://bit.ly/x',
        ]));

        $this->assertLessThanOrEqual((int) config('moderation.thresholds.cap'), $verdict->totalScore);
    }

    #[Test]
    public function alasan_diurutkan_dari_yang_paling_serius(): void
    {
        $verdict = $this->service()->inspect($this->context([
            'title' => 'Lomba',
            'description' => 'judol dan hubungi 081234567890',
        ]));

        $sorted = $verdict->sortedBySeverity();

        $this->assertSame('critical', $sorted[0]->severity->value, 'Temuan kritis harus muncul pertama.');
    }

    #[Test]
    public function ringkasan_terbaca_sebagai_bahasa_manusia(): void
    {
        $bersih = $this->service()->inspect($this->context())->summary();
        $bermasalah = $this->service()->inspect($this->context(['description' => 'judol']))->summary();

        $this->assertStringContainsString('admin', $bersih);
        $this->assertStringContainsString('Ditolak otomatis', $bermasalah);
    }

    #[Test]
    public function status_untuk_pengajuan_baru_selalu_pending_atau_rejected(): void
    {
        $result = $this->service()->statusForNewSubmission($this->context());

        $this->assertContains($result['status'], [EventStatus::Pending, EventStatus::Rejected]);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertArrayHasKey('verdict', $result);
    }
}
