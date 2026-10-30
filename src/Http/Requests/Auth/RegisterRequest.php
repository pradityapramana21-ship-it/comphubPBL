<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\FormRequest;

final class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|max:255|confirmed',
            'next' => 'nullable|string|max:500',
            'website' => 'max:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'kata sandi',
            'website' => 'situs web',
        ];
    }
}
