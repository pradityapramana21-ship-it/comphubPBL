<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori jenis kegiatan. MVP memakai tiga nilai utama: Lomba, Webinar,
 * Seminar. Subkategori (mis. Lomba → Akademik/Nasional) bisa menyusul
 * lewat baris baru tanpa mengubah skema — Panduan §6.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
