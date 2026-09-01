<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id'           => ['required', 'integer', 'exists:users,id', 'not_in:' . $this->user()->id],
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'scheduled_at'        => ['required', 'date', 'after:now'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'worker_id.required'           => 'A worker must be selected.',
            'worker_id.exists'             => 'The selected worker does not exist.',
            'worker_id.not_in'          => 'You cannot book yourself.',
            'service_category_id.required' => 'A service category must be selected.',
            'service_category_id.exists'   => 'The selected service category does not exist.',
            'scheduled_at.required'        => 'A scheduled date and time is required.',
            'scheduled_at.after'           => 'The scheduled date must be in the future.',
        ];
    }
}
