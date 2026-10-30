<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => 'required|string|max:255',
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:8|max:255|confirmed',
        ];
    }

    public function attributes(): array
    {
        return ['password' => 'kata sandi'];
    }
}
