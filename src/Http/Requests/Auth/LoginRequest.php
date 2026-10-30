<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\FormRequest;

final class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email|max:190',
            'password' => 'required|string|min:8|max:255',
            'remember' => 'nullable|boolean',
            'next' => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'email', 'password' => 'kata sandi'];
    }
}
