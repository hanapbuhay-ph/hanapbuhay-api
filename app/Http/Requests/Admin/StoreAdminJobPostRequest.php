<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminJobPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_profile_id'   => ['required', 'integer', 'exists:worker_profiles,id'],
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'title'               => ['required', 'string', 'max:100'],
            'description'         => ['required', 'string', 'max:500'],
            'rate_amount'         => ['required', 'numeric', 'min:0'],
            'rate_type'           => ['required', 'string', 'in:hourly,daily,weekly,monthly,per_session,per_project'],
            'is_available'        => ['required', 'boolean'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }
}
