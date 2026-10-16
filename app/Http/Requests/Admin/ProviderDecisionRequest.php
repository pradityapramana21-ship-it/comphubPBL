<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Keputusan verifikasi organisasi: setujui, tolak, atau kembalikan ke antrean.
 */
class ProviderDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $provider = $this->route()->parameter('provider');

        return $provider !== null && $this->user()?->can('verify', $provider) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['verify', 'reject', 'reopen'])],
            // Penolakan tanpa alasan membuat penyedia tidak mungkin
            // memperbaiki pengajuannya.
            'reason' => [
                'nullable', 'string', 'min:10', 'max:1000',
                'required_if:decision,reject',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'Tuliskan alasan penolakan agar organisasi tahu apa yang harus diperbaiki.',
            'reason.min' => 'Alasan minimal 10 karakter agar cukup jelas.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['decision' => 'keputusan', 'reason' => 'alasan'];
    }

    public function decision(): string
    {
        return (string) $this->input('decision');
    }

    public function reasonText(): ?string
    {
        $reason = trim((string) $this->input('reason'));

        return $reason === '' ? null : $reason;
    }
}
