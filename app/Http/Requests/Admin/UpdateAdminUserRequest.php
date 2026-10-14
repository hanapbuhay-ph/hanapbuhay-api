<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
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
        $target = User::find($this->route('id'));
        $assignableRoles = $target !== null && ! in_array($target->role, ['client', 'worker'], true)
            ? ['client', 'worker', 'admin']
            : ['client', 'worker'];

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
            'role' => ['sometimes', Rule::in($assignableRoles)],
            'barangay_id' => ['sometimes', 'required', 'integer', 'exists:barangays,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Client or worker accounts cannot be changed to an admin role.',
        ];
    }
}
