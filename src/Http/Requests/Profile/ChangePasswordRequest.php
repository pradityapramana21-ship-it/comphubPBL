<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Http\Requests\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => 'required|string|max:255',
            'password' => 'required|string|min:8|max:255|confirmed',
        ];
    }

    public function attributes(): array
    {
        return ['current_password' => 'kata sandi saat ini', 'password' => 'kata sandi baru'];
    }
}
