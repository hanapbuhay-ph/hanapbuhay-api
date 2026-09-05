<?php

namespace App\Http\Requests\JobPost;

use Illuminate\Foundation\Http\FormRequest;

class ReorderJobPostImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'min:1'],
            'image_ids.*' => ['required', 'integer'],
        ];
    }
}
