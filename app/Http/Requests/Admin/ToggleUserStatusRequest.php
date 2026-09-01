<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ToggleUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:suspend,reactivate'],
            'reason' => ['required_if:action,suspend', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.in'       => 'Action must be suspend or reactivate.',
            'reason.required_if' => 'A reason is required when suspending a user.',
        ];
    }
}
