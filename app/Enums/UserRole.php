<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Peran utama akun. Satu akun punya tepat satu role pada MVP
 * (Panduan §6.3 — tabel roles/user_roles baru dipakai jika satu
 * organisasi butuh beberapa akun atau satu orang punya dua peran).
 *
 * Role diperiksa DI SERVER pada setiap aksi terlindungi. Menyembunyikan
 * tombol di Blade bukan kontrol keamanan (Panduan §2).
 */
enum UserRole: string
{
    case User = 'user';
    case Provider = 'provider';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Pengguna',
            self::Provider => 'Penyedia Kegiatan',
            self::Admin => 'Administrator',
        };
    }

    /**
     * Ringkasan wewenang untuk halaman "Tentang" dan dokumen matriks role.
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::User => [
                'Melihat kegiatan yang sudah disetujui',
                'Mencari dan memfilter kegiatan',
                'Menyimpan atau membatalkan penyimpanan kegiatan',
                'Melaporkan kegiatan yang bermasalah',
            ],
            self::Provider => [
                'Mengelola profil organisasi',
                'Membuat draf dan mengajukan kegiatan',
                'Melihat status dan catatan moderasi',
                'Memperbaiki lalu mengajukan ulang kegiatan yang ditolak',
            ],
            self::Admin => [
                'Meninjau penyedia dan kegiatan',
                'Menyetujui, menolak, atau meminta perbaikan',
                'Mengarsipkan kegiatan dan menangani laporan',
                'Mengatur kategori serta membaca audit log',
            ],
        };
    }

    /**
     * Route middleware yang mengunci akses untuk role ini.
     */
    public function middleware(): string
    {
        return 'role:'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
