<?php

declare(strict_types=1);

namespace App\Services\Moderation;

use App\Models\ProviderProfile;

/**
 * Data yang sedang diperiksa.
 *
 * Dibuat sekali di awal proses moderasi, lalu dibaca semua aturan. Karena
 * readonly, tidak ada aturan yang bisa diam-diam mengubah inputs untuk
 * aturan berikutnya — yang membuat hasil bisa direproduksi.
 */
final readonly class ModerationContext
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $requirements,
        public string $registrationUrl,
        public ?string $platformUrl,
        public ?string $locationText,
        public \DateTimeInterface $eventStartAt,
        public \DateTimeInterface $eventEndAt,
        public \DateTimeInterface $registrationDeadline,
        public ProviderProfile $provider,
        /** null saat kegiatan baru; diisi saat pengajuan ulang/revisi. */
        public ?int $eventId = null,
    ) {}

    /**
     * Seluruh teks yang perlu diperiksa Rules berbasis kata.
     *
     * Dipisah dari metadata supaya satu perubahan di sini langsung terlihat
     * oleh semua aturan yang memakainya.
     */
    public function allFreeText(): string
    {
        return implode("\n", array_filter([
            $this->title,
            $this->description,
            $this->requirements ?? '',
            $this->locationText ?? '',
        ]));
    }

    /**
     * Seluruh tautan yang muncul di tempat yang ditampilkan ke pengguna.
     *
     * TIDAK memakai allFreeText() untuk yang berbasis URL: aturan tautan
     * mencurigakan hanya boleh menilai tautan yang bisa diklik.
     */
    public function allUrls(): array
    {
        $urls = array_filter([$this->registrationUrl, $this->platformUrl]);

        $fromText = $this->allFreeText();

        // PENTING: preg_match_all mengembalikan JUMLAH kecocokan, bukan
        // status berhasil. Versi sebelumnya memakai `=== 1`, yang hanya
        // benar bila ada tepat satu tautan — begitu deskripsi memuat dua
        // tautan atau lebih, hasilnya melompati blok ini dan tautan yang
        // ada di teks tidak pernah ikut diperiksa.
        if (preg_match_all('#https?://[^\s<>"\']+#iu', $fromText, $matches) > 0) {
            $urls = array_merge($urls, $matches[0]);
        }

        return array_values(array_unique(array_map('trim', $urls)));
    }

    /** Organization yang belum pernah punya riwayat pengajuan bersih. */
    public function providerIsTrusted(): bool
    {
        $this->provider->loadMissing('user');

        return $this->provider->isVerified();
    }
}
