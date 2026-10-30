<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Verifikasi Ditolak',
        };
    }

    public function canSubmitEvents(): bool
    {
        return $this === self::Verified;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
