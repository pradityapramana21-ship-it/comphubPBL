<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use App\Enums\EventMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi pengajuan kegiatan baru.
 *
 * Tiga lapis pemeriksaan terhadap tanggal:
 *
 *  1. Format (sudah bawaan aturan "date").
 *  2. Urutan antar-field (before_or_equal, after_or_equal).
 *  3. CHECK constraint di database, sebagai jaring pengaman terakhir kalau
 *     ada jalur yang lupa memanggil validasi ini.
 *
 * Aturan di sini TIDAK menggantikan CHECK constraint. Keduanya sengaja
 * ada: yang satu memberi pesan Bahasa Indonesia yang membantu, yang
 * lain memastikan integritas walau ada bug (Panduan §6.4).
 */
class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Migrasi: hanya penyedia yang sudah terverifikasi boleh membuat
        // kegiatan. Aturan yang sama ditegakkan EventPolicy::create().
        $user = $this->user();

        return $user !== null
            && $user->canAccessApplication()
            && $user->isProvider()
            && $user->hasVerifiedProvider();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'min:15', 'max:180'],
            'description' => ['required', 'string', 'min:120', 'max:8000'],
            'requirements' => ['nullable', 'string', 'max:3000'],

            'registration_url' => ['required', 'url:http,https', 'max:500'],

            'mode' => ['required', Rule::enum(EventMode::class)],
            'location_text' => ['nullable', 'string', 'max:255'],
            'platform_url' => ['nullable', 'url:http,https', 'max:500'],

            'event_start_at' => ['required', 'date'],
            'event_end_at' => ['required', 'date', 'after_or_equal:event_start_at'],
            'registration_deadline' => ['required', 'date', 'before_or_equal:event_start_at'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'category_id' => 'kategori',
            'title' => 'judul',
            'description' => 'deskripsi',
            'requirements' => 'syarat peserta',
            'registration_url' => 'tautan pendaftaran',
            'mode' => 'bentuk kegiatan',
            'location_text' => 'lokasi',
            'platform_url' => 'tautan daring',
            'event_start_at' => 'waktu mulai',
            'event_end_at' => 'waktu selesai',
            'registration_deadline' => 'tenggat pendaftaran',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'registration_url.url' => 'Tautan pendaftaran harus berupa tautan yang valid dan diawali http.',
            'title.min' => 'Judul minimal 15 karakter agar kegiatan mudah Searching.',
            'description.min' => 'Deskripsi minimal 120 karakter. Sebutkan kegiatan, target peserta, dan apa yang didapat.',
            'registration_deadline.before_or_equal' => 'Tenggat pendaftaran tidak boleh melewati waktu kegiatan dimulai.',
            'event_end_at.after_or_equal' => 'Waktu selesai tidak boleh sebelum waktu mulai.',
            'mode.required' => 'Pilih bentuk kegiatan: daring, luring, atau campuran.',
            'platform_url.url' => 'Tautan daring harus berupa tautan yang valid.',
        ];
    }

    /**
     * Pemeriksaan tambahan yang tidak bisa ditulis sebagai aturan biasa.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $mode = EventMode::tryFrom((string) $this->input('mode'));

            // Lokasi wajib untuk luring dan campuran. Sama persis dengan
            // CHECK constraint events_location_check di database.
            if ($mode?->requiresLocation() && blank($this->input('location_text'))) {
                $validator->errors()->add(
                    'location_text',
                    'Lokasi wajib diisi untuk kegiatan luring atau campuran.',
                );
            }

            // Tautan daring wajib untuk kegiatan yang sepenuhnya daring.
            if ($mode === EventMode::Online && blank($this->input('platform_url'))) {
                $validator->errors()->add(
                    'platform_url',
                    'Isi tautan daring (Zoom, Meet, dsb.) untuk kegiatan yang sepenuhnya daring.',
                );
            }

            // Tenggat tidak boleh terlalu dekat dengan waktu mulai.
            $start = $this->input('event_start_at');
            $deadline = $this->input('registration_deadline');

            if (is_string($start) && is_string($deadline)) {
                try {
                    $leadHours = (strtotime($start) - time()) / 3600;

                    if ($leadHours < 0) {
                        $validator->errors()->add(
                            'event_start_at',
                            'Waktu kegiatan sudah lewat. Pilih tanggal yang akan datang.',
                        );
                    }
                } catch (\Exception) {
                    // Biarkan aturan "date" yang melaporkan masalah format.
                }
            }
        });
    }

    /**
     * Data siap simpan. Status dan risk_score TIDAK ikut — keduanya
     * ditentukan mesin moderasi, bukan formulir.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        return [
            ...$this->safe()->only([
                'category_id', 'title', 'description', 'requirements',
                'registration_url', 'mode', 'location_text', 'platform_url',
                'event_start_at', 'event_end_at', 'registration_deadline',
            ]),
            // Honeypot terisi berarti bot; jangan diam-diam diterima.
            'website' => null,
        ];
    }
}
