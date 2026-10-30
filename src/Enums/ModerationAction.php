<?php

declare(strict_types=1);

namespace App\Enums;

enum ModerationAction: string
{
    case Submit = 'submit';
    case AutoFlagged = 'auto_flagged';
    case AutoRejected = 'auto_rejected';
    case Approve = 'approve';
    case Reject = 'reject';
    case RequestChanges = 'request_changes';
    case Archive = 'archive';
    case Expire = 'expire';
    case Unarchive = 'unarchive';

    public function label(): string
    {
        return match ($this) {
            self::Submit => 'Diajukan',
            self::AutoFlagged => 'Ditandai Otomatis',
            self::AutoRejected => 'Ditolak Otomatis',
            self::Approve => 'Disetujui',
            self::Reject => 'Ditolak',
            self::RequestChanges => 'Minta revisi',
            self::Archive => 'Diarsipkan',
            self::Expire => 'Kedaluwarsa',
            self::Unarchive => 'Dibuka Kembali',
        };
    }

    public function requiresAdmin(): bool
    {
        return match ($this) {
            self::Submit, self::AutoFlagged, self::AutoRejected, self::Expire => false,
            default => true,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
