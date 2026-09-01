<?php

namespace App\Http\Requests\JobPost;

use Illuminate\Foundation\Http\FormRequest;

class CreateJobPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate: auth:sanctum + worker middleware
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'title'               => ['required', 'string', 'max:100'],
            'description'         => ['required', 'string', 'max:500'],
            'rate_amount'         => ['required', 'numeric', 'min:0'],
            'rate_type'           => ['required', 'string', 'in:hourly,daily,weekly,monthly,per_session,per_project'],
            'is_available'        => ['sometimes', 'boolean'],
        ];
    }
}
