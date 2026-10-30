<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

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

    public function dateTime(\DateTimeInterface $moment): string
    {
        $local = $this->local($moment);

        return sprintf(
            '%d %s %s, %s.%s',
            (int) $local->format('j'),
            self::MONTHS_SHORT[(int) $local->format('n')],
            $local->format('Y'),
            $local->format('H'),
            $local->format('i'),
        );
    }

    public function date(\DateTimeInterface $moment): string
    {
        $local = $this->local($moment);

        return sprintf(
            '%d %s %s',
            (int) $local->format('j'),
            self::MONTHS_LONG[(int) $local->format('n')],
            $local->format('Y'),
        );
    }

    public function longDate(\DateTimeInterface $moment): string
    {
        $local = $this->local($moment);

        return sprintf(
            '%s, %d %s %s',
            self::DAYS[$local->format('l')] ?? $local->format('l'),
            (int) $local->format('j'),
            self::MONTHS_LONG[(int) $local->format('n')],
            $local->format('Y'),
        );
    }

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
                (int) $start->format('j'),
                self::MONTHS_LONG[(int) $start->format('n')],
                $start->format('Y'),
                $jam,
            );
        }

        if ($start->format('Y-m') === $end->format('Y-m')) {
            return sprintf(
                '%d-%d %s %s',
                (int) $start->format('j'),
                (int) $end->format('j'),
                self::MONTHS_LONG[(int) $start->format('n')],
                $start->format('Y'),
            );
        }

        return $this->date($from) . ' - ' . $this->date($to);
    }

    public function relative(\DateTimeInterface $moment): string
    {
        $today = $this->local(new \DateTimeImmutable('now'))->setTime(0, 0);
        $target = $this->local($moment)->setTime(0, 0);
        $days = (int) $today->diff($target)->format('%r%a');

        return match (true) {
            $days === 0 => 'hari ini',
            $days === 1 => 'besok',
            $days === -1 => 'kemarin',
            $days > 1 => $days . ' hari lagi',
            default => abs($days) . ' hari lalu',
        };
    }

    public function inputValue(\DateTimeInterface $moment): string
    {
        return $this->local($moment)->format('Y-m-d\TH:i');
    }

    public function machineValue(\DateTimeInterface $moment): string
    {
        return $this->local($moment)->format('Y-m-d');
    }

    public function toIso(\DateTimeInterface $moment): string
    {
        return $this->local($moment)->format(\DateTimeInterface::ATOM);
    }

    private function local(\DateTimeInterface $moment): \DateTimeImmutable
    {
        $timezone = $this->timezone();

        return (new \DateTimeImmutable($moment->format('Y-m-d H:i:s.u'), $moment->getTimezone()))
            ->setTimezone($timezone);
    }

    private function timezone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone((string) Config::get('app.timezone', 'Asia/Jakarta'));
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }
}
