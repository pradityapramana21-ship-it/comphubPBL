<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->user()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [
                'required', 'string', 'email:rfc', 'max:190',
                // Unik terhadap baris lain, bukan terhadap dirinya sendiri —
                // tanpa except, user tidak akan bisa menyimpan profilnya
                // tanpa mengubah email.
                Rule::unique('users', 'email')->ignore($userId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nama', 'email' => 'email'];
    }

    /** @return array<string, mixed> */
    public function profileAttributes(): array
    {
        return $this->safe()->only(['name', 'email']);
    }
}
