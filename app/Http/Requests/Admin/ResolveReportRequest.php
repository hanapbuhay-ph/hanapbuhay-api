<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'      => ['required', 'string', 'in:resolved,dismissed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            // Optional enforcement action applied to the reported user
            'action'      => ['sometimes', 'nullable', 'string', 'in:suspend_user,revoke_trust_tier,warn_user'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The status field is required.',
            'status.in'       => 'Status must be either resolved or dismissed.',
            'action.in'       => 'Action must be suspend_user, revoke_trust_tier, or warn_user.',
        ];
    }
}
