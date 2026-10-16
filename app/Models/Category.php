<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori jenis kegiatan: Lomba, Webinar, Seminar (dan subkategori bila perlu).
 *
 * Di-route memakai `slug`, bukan `id`, karena URL kategori tampil di publik
 * dan slug lebih enak dibaca.
 */
#[Fillable(['name', 'slug', 'description', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * Default di level model, bukan hanya di level database, supaya
     * instance yang baru dibuat sudah benar tanpa perlu refresh() dulu.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Jumlah kegiatan yang sedang tayang — ditampilkan di chip filter.
     * Dipanggil hanya untuk daftar kategori (jumlah kecil), bukan per kartu.
     */
    public function publishedEventsCount(): int
    {
        return $this->events()->where('status', 'approved')->count();
    }
}
