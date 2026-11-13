<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // API tìm kiếm việc làm công khai (Public / Candidate)
    }

    public function rules(): array
    {
        return [
            'keyword'            => ['nullable', 'string', 'max:150'],
            'job_group_id'       => ['nullable', 'integer', 'min:1'],
            'employment_type_id' => ['nullable', 'integer', 'min:1'],
            'work_mode_id'       => ['nullable', 'integer', 'min:1'],
            'seniority_level_id' => ['nullable', 'integer', 'min:1'],
            'page'               => ['nullable', 'integer', 'min:1'],
            'page_size'          => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
