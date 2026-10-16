<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relasi "user menyimpan kegiatan".
 *
 * CATATAN PENTING: tabel ini memakai PRIMARY KEY komposit (user_id, event_id)
 * dan TIDAK punya kolom `id`. Eloquent hanya mendukung satu primary key,
 * jadi model ini tidak boleh dipakai untuk `save()` pada model yang sudah ada
 * — gunakan `BookmarkRepository` yang memang bekerja lewat query builder.
 *
 * Kelebihan PK komposit: klik "Simpan" dua kali secara bersamaan ditolak
 * database, tanpa perlu cek-then-insert yang rawan race condition
 * (Panduan FR-05, §9.2).
 */
class Bookmark extends Model
{
    public $incrementing = false;

    /** Tidak ada satu primary key pun; kunci ditentukan oleh kombinasi kolom. */
    protected $primaryKey = null;

    public $timestamps = false;

    protected $table = 'bookmarks';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
