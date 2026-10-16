<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Format tanggal dalam Bahasa Indonesia.
 *
 * Semua kolom waktu disimpan sebagai TIMESTAMPTZ dalam UTC, dan hanya
 * tampilannya yang memakai zona waktu aplikasi (Panduan §6.4). Karena itu
 * setiap konversi ke teks harus lewat kelas ini — tidak boleh ada
 * toFormattedDateString() atau ->format() yang berdiri sendiri di view,
 * supaya format tanggal di seluruh aplikasi tidak berbeda-beda.
 */
final class DateHelper
{
    private const MONTHS_SHORT = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    private const MONTHS_LONG = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private const DAYS = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    /**
     * "12 Nov 2026, 09.30"
     */
    public function dateTime(\DateTimeInterface $moment): string
    {
        $local = $this->local($moment);

        return sprintf(
            '%d %s %s, %s.%s',
            $local->day,
            self::MONTHS_SHORT[(int) $local->month],
            $local->year,
            $local->format('H'),
            $local->format('i'),
        );
    }

    /**
     * "12 November 2026"
     */
    public function date(\DateTimeInterface $moment): string
    {
        $local = $this->local($moment);

        return sprintf(
            '%d %s %s',
            $local->day,
            self::MONTHS_LONG[(int) $local->month],
            $local->year,
        );
    }

    /**
     * "Rabu, 12 November 2026"
     */
    public function longDate(\DateTimeInterface $moment): string
    {
        $local = $this->local($moment);

        return sprintf(
            '%s, %d %s %s',
            self::DAYS[$local->format('l')],
            $local->day,
            self::MONTHS_LONG[(int) $local->month],
            $local->year,
        );
    }

    /**
     * Rentang tanggal yang enak dibaca.
     *
     * Tiga bentuk, tergantung seberapa jauh jaraknya:
     *   satu hari, jam berbeda : "10 Mei 2026, 09.00-14.30"
     *   satu bulan              : "10-11 Mei 2026"
     *   bulan atau tahun beda   : "10 Mei 2026 - 12 Juni 2026"
     *
     * Jam SELESAI tetap ditampilkan walaupun acaranya cuma sehari.
     * Melewatkannya membuat seminar 09.00-14.30 tertulis sebagai
     * "09.00" saja, dan orang bisa salah datang sejam.
     */
    public function range(\DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $start = $this->local($from);
        $end = $this->local($to);

        if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
            $jam = $start->format('H.i') === $end->format('H.i')
                ? $start->format('H.i')
                : $start->format('H.i') . '-' . $end->format('H.i');

            return sprintf(
                '%d %s %s, %s',
                $start->day,
                self::MONTHS_LONG[(int) $start->month],
                $start->year,
                $jam,
            );
        }

        if ($start->year === $end->year && $start->month === $end->month) {
            return sprintf(
                '%d-%d %s %s',
                $start->day,
                $end->day,
                self::MONTHS_LONG[(int) $start->month],
                $start->year,
            );
        }

        return $this->date($from) . ' - ' . $this->date($to);
    }

    /**
     * "3 hari lagi", "hari ini", "2 hari lalu".
     */
    public function relative(\DateTimeInterface $moment): string
    {
        $days = (int) now()->startOfDay()->diffInDays($this->local($moment)->startOfDay(), false);

        return match (true) {
            $days === 0 => 'hari ini',
            $days === 1 => 'besok',
            $days === -1 => 'kemarin',
            $days > 1 => $days . ' hari lagi',
            default => abs($days) . ' hari lalu',
        };
    }

    /**
     * Nilai untuk atribut <input type="datetime-local">, yang butuh
     * format "Y-m-dTH:i" tanpa zona waktu.
     */
    public function inputValue(\DateTimeInterface $moment): string
    {
        return $this->local($moment)->format('Y-m-d\TH:i');
    }

    /**
     * Atribut datetime untuk <time> dan penyARING tanggal untuk
     * <input type="date">.
     */
    public function machineValue(\DateTimeInterface $moment): string
    {
        return $this->local($moment)->format('Y-m-d');
    }

    /**
     * Selalu mengembalikan Carbon.
     *
     * DateTimeInterface "murni" tidak punya properti ->day / ->month /
     * ->year, dan juga tidak punya ->startOfDay(). Versi sebelumnya
     * mengembalikan tipe itu apa adanya, jadi pemanggilan di atas
     * menghasilkan warning dan tanggal kosong.
     */
    private function local(\DateTimeInterface $moment): Carbon
    {
        return Carbon::instance($moment)->setTimezone($this->timezone());
    }

    private function timezone(): \DateTimeZone
    {
        $name = (string) config('app.timezone', 'UTC');

        try {
            return new \DateTimeZone($name);
        } catch (\Exception) {
            // Konfigurasi rusak tidak boleh membuat setiap halaman 500.
            return new \DateTimeZone('UTC');
        }
    }
}
