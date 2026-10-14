<?php

namespace App\Http\Requests\Auth;

use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile_number') && $this->input('mobile_number') !== null) {
            $this->merge([
                'mobile_number' => PhilippineMobileNumber::normalize($this->input('mobile_number')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'email'         => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'mobile_number' => ['sometimes', 'string', new PhilippineMobileNumber],
            'barangay_id'   => ['sometimes', 'integer', 'exists:barangays,id'],
            'profile_photo' => ['sometimes', 'image', 'mimes:jpeg,png', 'max:2048'],
        ];
    }
}
