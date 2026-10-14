<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('id')),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'mobile_number' => ['sometimes', 'nullable', 'string', new PhilippineMobileNumber],
            'role' => ['sometimes', Rule::in(['client', 'worker', 'admin'])],
            'barangay_id' => ['sometimes', 'required', 'integer', 'exists:barangays,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
