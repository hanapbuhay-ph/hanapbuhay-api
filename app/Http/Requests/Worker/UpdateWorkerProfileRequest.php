<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bio'                   => ['nullable', 'string', 'max:1000'],
            'availability_status'   => ['nullable', 'in:available,busy,offline'],
            'category_ids'          => ['nullable', 'array'],
            'category_ids.*'        => ['integer', 'exists:service_categories,id'],
            'portfolio_photos'      => ['nullable', 'array'],
            'portfolio_photos.*'    => ['file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability_status.in'      => 'Availability status must be available, busy, or offline.',
            'category_ids.array'          => 'Category IDs must be an array.',
            'category_ids.*.exists'       => 'One or more selected categories do not exist.',
            'portfolio_photos.*.image'    => 'Each portfolio photo must be an image file.',
            'portfolio_photos.*.max'      => 'Each portfolio photo must not exceed 5MB.',
        ];
    }
}
