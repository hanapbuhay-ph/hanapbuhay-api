<?php

namespace App\Http\Requests\Report;

use App\Models\ReportReason;
use Illuminate\Foundation\Http\FormRequest;

class CreateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $activeReasonCodes = ReportReason::query()
            ->where('is_active', true)
            ->pluck('code')
            ->all();

        return [
            'booking_id'        => ['required', 'integer', 'exists:bookings,id'],
            'reported_user_id'  => ['required', 'integer', 'exists:users,id'],
            'reason'            => ['required', 'string', 'in:' . implode(',', $activeReasonCodes)],
            'description'       => ['required', 'string', 'max:2000'],
            'evidence_photos'   => ['nullable', 'array', 'max:3'],
            'evidence_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
