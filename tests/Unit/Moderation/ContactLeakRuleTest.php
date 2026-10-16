<?php

declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Services\Moderation\Rules\ContactLeakRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ContactLeakRuleTest extends ModerationRuleTestCase
{
    protected function rule(): ContactLeakRule
    {
        return $this->app->make(ContactLeakRule::class);
    }

    #[Test]
    public function tanpa_kontak_langsung_tidak_menyala(): void
    {
        $this->assertFalse($this->fires());
    }

    /** @return array<string, array{string, string}> */
    public static function contactProvider(): array
    {
        return [
            'nomor hp' => ['Hubungi 081234567890 untuk pendaftaran', 'whatsapp'],
            'telepon' => ['Telepon 081234567890 ASAP', 'phone_generic'],
            'telegram' => ['Detail di t.me/bemkampus', 'telegram'],
            'email' => ['Kirim ke secretive@contoh.test', 'email'],
        ];
    }

    #[Test]
    #[DataProvider('contactProvider')]
    public function kontak_langsung_menyalakan_aturan(string $description, string $expectedType): void
    {
        $result = $this->evaluate(['description' => $description]);

        $this->assertNotNull($result);
        $types = array_column($result->metadata['findings'], 'type');
        $this->assertContains($expectedType, $types);
    }

    #[Test]
    public function nilai_kontak_asli_tidak_disimpan(): void
    {
        // Ini yang paling penting: log moderasi tidak boleh menyalin
        // nomor telepon ke dalam metadata yang bisa bocor.
        $result = $this->evaluate(['description' => 'Hubungi 081234567890']);

        $encoded = json_encode($result->metadata);

        $this->assertStringNotContainsString('081234567890', $encoded);
        $this->assertStringNotContainsString('0812', $encoded);
    }

    #[Test]
    public function email_resmi_pada_tautan_pendaftaran_tidak_ditandai(): void
    {
        // Konfigurasi default: email pada registration_url boleh, karena
        // tautan pendaftaran sering berupa mailto.
        $this->assertFalse($this->fires([
            'registrationUrl' => 'mailto:info@kampus.ac.id',
        ]));
    }

    #[Test]
    public function email_di_tautan_pendaftaran_bisa_diperketat(): void
    {
        config()->set('moderation.contact_leak.check_registration_url_for_email', true);

        $this->assertTrue($this->fires([
            'registrationUrl' => 'https://kampus.ac.id/daftar?email=info@kampus.ac.id',
        ]), 'Saat diketat, email pada tautan ikut diperiksa.');
    }

    #[Test]
    public function bobotnya_rendah_dan_tidak_menolak_sendiri(): void
    {
        $result = $this->evaluate(['description' => 'Hubungi 081234567890']);

        $this->assertSame('low', $result->severity->value);
        $this->assertLessThan(
            (int) config('moderation.thresholds.reject'),
            $result->score,
            'Kontak yang bocor saja tidak cukup untuk menolak pengajuan.',
        );
    }
}
