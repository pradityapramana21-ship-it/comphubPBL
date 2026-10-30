<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email|max:190'];
    }
}
