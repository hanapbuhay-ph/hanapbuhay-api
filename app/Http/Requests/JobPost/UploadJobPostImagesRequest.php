<?php

namespace App\Http\Requests\JobPost;

use Illuminate\Foundation\Http\FormRequest;

class UploadJobPostImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate: auth:sanctum + worker middleware
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240', 'image'],
        ];
    }
}
