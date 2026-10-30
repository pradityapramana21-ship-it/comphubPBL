<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Core\Validator;
use App\Http\Requests\FormRequest;

final class SubmitEventRequest extends FormRequest
{
    public function rules(): array
    {
        $revision = (bool) $this->request->attribute('revision_event', false);
        $file = $revision ? 'nullable|file' : 'required|file';

        return [
            'event_type' => 'required|in:competition,webinar,seminar',
            'title' => 'required|string|min:5|max:180',
            'organizer_name' => 'required|string|min:3|max:180',
            'organizer_email' => 'required|email|max:190',
            'contact_number' => 'required|string|min:8|max:40',
            'category_id' => 'required|integer|exists:categories,id',
            'description' => 'required|string|min:40|max:10000',
            'requirements' => 'nullable|string|max:5000',
            'registration_url' => 'required|url|max:500',
            'source_url' => 'nullable|url|max:500',
            'mode' => 'required|in:online,offline,hybrid',
            'location_text' => 'nullable|string|max:255',
            'platform_url' => 'nullable|url|max:500',
            'registration_start_at' => 'nullable|date',
            'registration_deadline' => 'required|date|before_or_equal:event_start_at',
            'event_start_at' => 'required|date|after:now',
            'event_end_at' => 'required|date|after_or_equal:event_start_at',
            'poster' => $file . '|mimetypes:jpeg,jpg,png,webp|max:2048',
            'guidebook' => 'nullable|file|mimetypes:pdf|max:5120',
            'website' => 'max:0',
        ];
    }

    protected function after(Validator $validator): void
    {
        $mode = (string) $this->input('mode', '');

        if (in_array($mode, ['offline', 'hybrid'], true) && trim((string) $this->input('location_text', '')) === '') {
            $validator->addError('location_text', 'Lokasi wajib diisi untuk kegiatan offline atau hybrid.');
        }

        if ((string) $this->input('event_type') === 'competition') {
            $guidebook = $this->request->file('guidebook');
            $hasExisting = (bool) $this->request->attribute('has_existing_guidebook', false);
            $hasGuidebook = is_array($guidebook) && ($guidebook['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

            if (! $hasExisting && ! $hasGuidebook) {
                $validator->addError('guidebook', 'Panduan PDF wajib diunggah untuk lomba.');
            }
        }
    }

    public function attributes(): array
    {
        return [
            'event_type' => 'jenis kegiatan',
            'title' => 'judul kegiatan',
            'organizer_name' => 'nama penyelenggara',
            'organizer_email' => 'email penyelenggara',
            'contact_number' => 'nomor kontak',
            'category_id' => 'topik',
            'description' => 'deskripsi',
            'registration_url' => 'tautan pendaftaran',
            'source_url' => 'tautan sumber',
            'mode' => 'mode kegiatan',
            'location_text' => 'lokasi',
            'registration_start_at' => 'waktu pendaftaran dibuka',
            'registration_deadline' => 'tenggat pendaftaran',
            'event_start_at' => 'waktu mulai',
            'event_end_at' => 'waktu selesai',
            'poster' => 'poster',
            'guidebook' => 'panduan',
            'website' => 'situs web',
        ];
    }
}
