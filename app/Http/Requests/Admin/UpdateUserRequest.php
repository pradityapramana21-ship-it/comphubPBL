<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tindakan administratif atas sebuah akun: status dan peran.
 *
 * Admin TIDAK boleh menyentuh akun admin lain. Aturan itu ditegakkan
 * UserPolicy, dan diulang lagi di sini supaya pesannya jelas.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        if (! $target instanceof User) {
            return false;
        }

        $actor = $this->user();

        if ($actor === null || ! $actor->isAdmin() || $actor->id === $target->id) {
            return false;
        }

        return $target->role !== UserRole::Admin;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'account_status' => ['sometimes', Rule::enum(AccountStatus::class)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'suspension_note' => [
                'nullable', 'string', 'max:500',
                'required_if:account_status,suspended',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'suspension_note.required_if' => 'Tuliskan alasan penangguhan agar bisa disampaikan ke pengguna.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['account_status' => 'status akun', 'role' => 'peran', 'suspension_note' => 'catatan penangguhan'];
    }
}
