<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Satu aturan moderasi.
 *
 * Setiap aturan berdiri sendiri: punya kunci, punya tingkat keparahan, dan
 * memeriksa satu hal. Menambah aturan baru berarti membuat kelas baru dan
 * mendaftarkannya — tidak menyentuh kode yang sudah ada. Itu yang membuat
 * daftar aturan bisa dibaca admin dan diuji satu per satu.
 *
 * Aturan HARUS deterministik: input yang sama menghasilkan output yang sama.
 * Kalau sebuah aturan butuh menebak, itu bukan aturan moderasi, itu heuristics
 * yang harus punya versi dan pengujian sendiri.
 */
interface ModerationRule
{
    /** Kunci aturan, sama dengan kunci bobotnya di config/moderation.php. */
    public function key(): string;

    /** Tingkat keparahan temuan kalau aturan ini menyala. */
    public function severity(): \App\Enums\RuleSeverity;

    /**
     * Periksa satu pengajuan.
     *
     * @return RuleResult|null null berarti tidak ada temuan
     */
    public function evaluate(ModerationContext $context): ?RuleResult;
}
