<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Pendaftaran sebagai pengguna biasa.
 *
 * TIDAK ADA field role. Kalau ada, siapa pun bisa menulis role=admin di
 * form dan mencari akun administrator. FormRequest tidak boleh mempercayai
 * select yang dibuat sendiri — role hanya bisa ditetapkan lewat
 * AuthService (Panduan §4.5).
 */
class RegisterRequest extends FormRequest
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
            // honeypot: field yang tidak pernah dilihat orang, tapi akan
            // terisi kalau form diisi bot.
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'website.max' => 'Permintaan ditolak.',
            'name.min' => 'Nama minimal 3 karakter.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
        ];
    }
}
