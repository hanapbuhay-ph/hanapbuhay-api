<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateAdminBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'worker_id' => ['required', 'integer', 'exists:users,id', 'different:client_id'],
            'job_post_id' => ['required', 'integer', 'exists:job_posts,id'],
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
