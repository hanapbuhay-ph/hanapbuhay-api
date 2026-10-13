<?php

namespace App\Http\Requests\Admin;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reporterRole = $this->input('reporter_role');

        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'reporter_role' => ['required', 'string', Rule::in(['client', 'worker'])],
            'reason' => [
                'required',
                'string',
                Rule::exists('report_reasons', 'code')->where(
                    fn (Builder $query) => $query
                        ->where('audience', $reporterRole)
                        ->where('is_active', true)
                ),
            ],
            'description' => ['required', 'string', 'max:2000'],
            'evidence_photos' => ['nullable', 'array', 'max:3'],
            'evidence_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
