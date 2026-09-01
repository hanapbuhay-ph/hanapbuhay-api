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
            'status'            => ['required', 'string', 'in:resolved,dismissed'],
            'admin_notes'       => ['nullable', 'string', 'max:2000'],
            // Spec §K13 field name
            'resolution_action' => ['sometimes', 'nullable', 'string', 'in:warning_issued,account_suspended,verification_revoked,no_action'],
            // Legacy field name (backwards compat)
            'action'            => ['sometimes', 'nullable', 'string', 'in:suspend_user,revoke_trust_tier,warn_user'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'The status field is required.',
            'status.in'       => 'Status must be either resolved or dismissed.',
            'resolution_action.in' => 'resolution_action must be warning_issued, account_suspended, verification_revoked, or no_action.',
            'action.in'       => 'action must be suspend_user, revoke_trust_tier, or warn_user.',
        ];
    }
}
