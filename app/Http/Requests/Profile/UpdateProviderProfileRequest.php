<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderProfileRequest extends FormRequest
{
    /**
     * Rute profil organisasi tidak punya parameter rute sama sekali —
     * profil ditentukan dari akun yang sedang masuk. Mengambil profil
     * dari parameter rute membuat authorize() selalu bernilai false,
     * dan pennyedia tidak pernah bisa menyimpan perubahannya.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $profile = $user?->providerProfile;

        return $profile !== null
            && $user->can('update', $profile) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'min:3', 'max:150'],
            'campus_name' => ['required', 'string', 'min:3', 'max:150'],
            'official_email' => ['required', 'string', 'email:rfc', 'max:190'],
            'website_url' => ['nullable', 'url:http,https', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],

            // Bukti organisasi boleh diperbarui kapan saja; status
            // status verifikasinya sendiri tetap di tangan admin.
            'verification_document' => [
                'nullable', 'file', 'max:2048',
                'mimetypes:application/pdf,image/jpeg,image/png',
            ],

            'resubmit' => ['nullable', 'boolean'],
            'verification_note' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'organization_name' => 'nama organisasi',
            'campus_name' => 'nama kampus',
            'official_email' => 'email resmi organisasi',
            'website_url' => 'situs organisasi',
            'description' => 'deskripsi organisasi',
            'verification_document' => 'dokumen verifikasi',
        ];
    }

    /** @return array<string, mixed> */
    public function profileAttributes(): array
    {
        return $this->safe()->only([
            'organization_name', 'campus_name',
            'official_email', 'website_url', 'description',
        ]);
    }
}
