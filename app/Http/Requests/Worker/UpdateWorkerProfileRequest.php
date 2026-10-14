<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
            'availability_status' => ['nullable', 'in:available,busy,offline'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:service_categories,id'],
            'sync_categories' => ['sometimes', 'boolean'],
            'keep_portfolio_photo_ids' => ['nullable', 'array', 'max:6'],
            'keep_portfolio_photo_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('worker_portfolio_photos', 'id')
                    ->where('worker_profile_id', $this->user()?->workerProfile?->id),
            ],
            'sync_portfolio_photos' => ['sometimes', 'boolean'],
            'portfolio_photos' => ['nullable', 'array', 'max:6'],
            'portfolio_photos.*' => ['file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability_status.in' => 'Availability status must be available, busy, or offline.',
            'category_ids.array' => 'Category IDs must be an array.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
            'keep_portfolio_photo_ids.*.exists' => 'One or more selected portfolio photos are invalid.',
            'portfolio_photos.max' => 'A maximum of six portfolio photos is allowed.',
            'portfolio_photos.*.image' => 'Each portfolio photo must be an image file.',
            'portfolio_photos.*.max' => 'Each portfolio photo must not exceed 5MB.',
        ];
    }
}
