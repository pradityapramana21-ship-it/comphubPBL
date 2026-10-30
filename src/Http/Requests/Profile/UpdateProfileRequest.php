<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Http\Requests\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        $userId = app()->auth()->id() ?? 0;

        return [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:190|unique:users,email,' . $userId,
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'email' => 'email'];
    }
}
