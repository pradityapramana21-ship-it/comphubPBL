<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event !== null && $this->user()?->can('report', $event) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason_code' => ['required', Rule::enum(ReportReason::class)],
            // Alasan "lainnya" tanpa keterangan tidak berguna.
            'description' => [
                'nullable', 'string', 'max:1000',
                'required_if:reason_code,other',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'description.required_if' => 'Tuliskan keterangan untuk alasan "Lainnya".',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason_code' => 'alasan', 'description' => 'keterangan'];
    }

    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->input('reason_code'));
    }
}
