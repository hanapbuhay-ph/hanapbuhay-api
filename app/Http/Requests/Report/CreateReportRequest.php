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
        $audience = $this->user()?->role === 'worker' ? 'worker' : 'client';
        $activeReasonCodes = ReportReason::query()
            ->where('is_active', true)
            ->where('audience', $audience)
            ->pluck('code')
            ->all();

        if ($audience === 'worker') {
            $activeReasonCodes = array_merge($activeReasonCodes, [
                'no_show', 'unsatisfactory_work', 'misconduct', 'non_payment',
                'unsafe_environment', 'abusive_behavior', 'false_information', 'other',
            ]);
        }

        return [
            'booking_id'        => ['required', 'integer', 'exists:bookings,id'],
            'reported_user_id'  => ['required', 'integer', 'exists:users,id'],
            'reason'            => ['required', 'string', 'in:' . implode(',', array_unique($activeReasonCodes))],
            'description'       => ['required', 'string', 'max:2000'],
            'evidence_photos'   => ['nullable', 'array', 'max:3'],
            'evidence_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
