<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class CreateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id'        => ['required', 'integer', 'exists:bookings,id'],
            'reported_user_id'  => ['required', 'integer', 'exists:users,id'],
            'reason'            => ['required', 'string', 'in:no_show,misconduct,unsatisfactory_work,non_payment,unsafe_environment,abusive_behavior,false_information,other'],
            'description'       => ['required', 'string', 'max:2000'],
            'evidence_photos'   => ['nullable', 'array', 'max:3'],
            'evidence_photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
