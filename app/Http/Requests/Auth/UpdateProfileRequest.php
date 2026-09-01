<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'mobile_number' => ['sometimes', 'string', 'max:20'],
            'barangay_id'   => ['sometimes', 'integer', 'exists:barangays,id'],
            'profile_photo' => ['sometimes', 'image', 'mimes:jpeg,png', 'max:2048'],
        ];
    }
}
