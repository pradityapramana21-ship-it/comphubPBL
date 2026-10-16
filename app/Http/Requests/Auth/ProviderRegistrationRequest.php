<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Pendaftaran sebagai penyedia kegiatan.
 *
 * Berkas bukti organisasi bersifat opsional. Panduan §7.2 dengan tegas
 * menyatakan bukti dokumen hanya diminta bila perlu — memaksakannya
 * menambah langkah tanpa menambah kepercayaan, karena dokumen palsu
 * gampang dibuat.
 */
class ProviderRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            'organization_name' => ['required', 'string', 'min:3', 'max:150'],
            'campus_name' => ['required', 'string', 'min:3', 'max:150'],
            'official_email' => ['required', 'string', 'email:rfc', 'max:190'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            // Validasi berkas: tipe MIME dibaca dari isi berkas, bukan dari
            // nama yang diunggah pengguna (Panduan §8, OWASP File Upload).
            'verification_document' => [
                'nullable',
                'file',
                'max:2048',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],

            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'organization_name' => 'nama organisasi',
            'campus_name' => 'nama kampus',
            'official_email' => 'email resmi organisasi',
            'website_url' => 'situs organisasi',
            'description' => 'deskripsi organisasi',
            'verification_document' => 'dokumen verifikasi',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'verification_document.mimetypes' => 'Dokumen harus berupa berkas PDF, JPG, atau PNG.',
            'verification_document.max' => 'Ukuran dokumen maksimal 2 MB.',
            'website_url.url' => 'Situs organisasi harus berupa tautan yang valid.',
            'website.max' => 'Permintaan ditolak.',
        ];
    }

    /** Data yang aman diteruskan ke service. */
    public function providerAttributes(): array
    {
        return $this->safe()->only([
            'name', 'email', 'password',
            'organization_name', 'campus_name',
            'official_email', 'website_url', 'description',
        ]);
    }
}
