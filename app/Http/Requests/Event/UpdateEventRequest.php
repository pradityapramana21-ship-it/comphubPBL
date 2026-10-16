<?php

declare(strict_types=1);

namespace App\Http\Requests\Event;

use App\Enums\EventMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi penyuntingan kegiatan milik sendiri.
 *
 * Bedanya dengan StoreEventRequest: field organisasi TIDAK boleh diubah.
 * Catatan penting: kegiatan yang sudah tayang tidak boleh disunting
 * langsung — penyedia harus menarik dulu supaya kembali ke
 * antrean review. Kalau itu dilewati, siapa pun bisa membuka kegiatan
 * yang sudah disetujui lalu mengganti isinya tanpa ditinjau.
 */
class UpdateEventRequest extends FormRequest
{
    /**
     * Di sini hanya dicek "apakah dia boleh menyentuh form ini".
     *
     * Kepemilikan SENGAJA tidak diperiksa di tahap ini. Kalau diperiksa,
     * Form Request menjawab 403 lebih dulu, padahal untuk kegiatan milik
     * orang lain jawaban yang benar adalah 404 supaya keberadaannya tidak
     * bocor. Pemeriksaan kepemilikan tetap ada, hanya dipindah ke
     * ProviderEventController::ownEvent() supaya jawabannya konsisten.
     */
    public function authorize(): bool
    {
        return $this->user()?->isProvider() === true
            && $this->user()->canAccessApplication();
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

            // honeypot
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (new StoreEventRequest)->attributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return (new StoreEventRequest)->messages();
    }

    public function withValidator(Validator $validator): void
    {
        $mode = EventMode::tryFrom((string) $this->input('mode'));

        if ($mode?->requiresLocation() && blank($this->input('location_text'))) {
            $validator->errors()->add('location_text', 'Lokasi wajib diisi untuk kegiatan luring atau campuran.');
        }

        if ($mode === EventMode::Online && blank($this->input('platform_url'))) {
            $validator->errors()->add('platform_url', 'Isi tautan daring untuk kegiatan yang sepenuhnya daring.');
        }

        $start = $this->input('event_start_at');

        if (is_string($start) && strtotime($start) < time()) {
            $validator->errors()->add('event_start_at', 'Waktu kegiatan sudah lewat. Pilih tanggal yang akan datang.');
        }
    }

    /** @return array<string, mixed> */
    public function eventAttributes(): array
    {
        return $this->safe()->only([
            'category_id', 'title', 'description', 'requirements',
            'registration_url', 'mode', 'location_text', 'platform_url',
            'event_start_at', 'event_end_at', 'registration_deadline',
        ]);
    }
}
