<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteJobPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'post_ids' => ['required', 'array', 'min:1'],
            'post_ids.*' => ['required', 'integer', 'distinct', 'exists:job_posts,id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
