<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EventStatus;
use App\Enums\ModerationAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Keputusan admin atas satu kegiatan: setujui, tolak, atau minta perbaikan.
 *
 * Satu FormRequest untuk tiga aksi karena ketiganya memakai field yang sama
 * dan aturan yang sama: alasan wajib ada setiap kali kegiatan TIDAK
 * disetujui (Panduan FR-08).
 */
class ModerationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event !== null && $this->user()?->can('moderate', $event) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ModerationAction::class)],

            // Alasan wajib untuk Reject dan RequestChanges, opsional untuk
            // Approve. Aturan "required_if" di sini adalah padanan awal;
            // EventService memeriksa ulang di server.
            'reason' => [
                'nullable', 'string', 'min:10', 'max:1000',
                'required_if:decision,reject,request_changes',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'Tuliskan alasan keputusan. Penyedia harus tahu apa yang perlu diperbaiki.',
            'reason.min' => 'Alasan minimal 10 karakter agar cukup jelas.',
            'decision.in' => 'Keputusan tidak dikenal.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['decision' => 'keputusan', 'reason' => 'alasan'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $decision = ModerationAction::tryFrom((string) $this->input('decision'));

            if ($decision !== null && $decision->requiresAdmin()) {
                // accept() sudah memeriksa authorize(), tapi pengaman
                // kedua deliberate: keputusan moderasi adalah aksi paling
                // berdampak di aplikasi ini.
                if (! $this->user()?->isAdmin()) {
                    $validator->errors()->add('decision', 'Hanya administrator yang dapat memutuskan.');
                }
            }
        });
    }

    public function action(): ModerationAction
    {
        return ModerationAction::from((string) $this->input('decision'));
    }

    /**
     * Status tujuan dari aksi yang dipilih.
     */
    public function targetStatus(): ?EventStatus
    {
        return match ($this->action()) {
            ModerationAction::Approve => EventStatus::Approved,
            ModerationAction::Reject => EventStatus::Rejected,
            ModerationAction::RequestChanges => EventStatus::ChangesRequested,
            ModerationAction::Archive => EventStatus::Archived,
            default => null,
        };
    }

    public function reasonText(): ?string
    {
        $reason = trim((string) $this->input('reason'));

        return $reason === '' ? null : $reason;
    }
}
