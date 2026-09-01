<?php

namespace App\Http\Requests\JobPost;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'string', 'max:100'],
            'description'  => ['sometimes', 'string', 'max:500'],
            'rate_amount'  => ['sometimes', 'numeric', 'min:0'],
            'rate_type'    => ['sometimes', 'string', 'in:hourly,daily,weekly,monthly,per_session,per_project'],
            'is_available' => ['sometimes', 'boolean'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
