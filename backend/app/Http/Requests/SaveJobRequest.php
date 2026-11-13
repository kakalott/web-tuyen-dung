<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'company';
    }

    public function rules(): array
    {
        return [
            'title'              => ['required', 'string', 'max:200'],
            'description'        => ['required', 'string', 'max:20000'],
            'requirements_text'  => ['required', 'string', 'max:20000'],

            'job_group_id'       => ['required', 'integer', Rule::exists('job_groups', 'id')->where('is_active', 1)],
            'employment_type_id' => ['required', 'integer', Rule::exists('employment_types', 'id')->where('is_active', 1)],
            'work_mode_id'       => ['required', 'integer', Rule::exists('work_modes', 'id')->where('is_active', 1)],
            'seniority_level_id' => ['required', 'integer', Rule::exists('seniority_levels', 'id')->where('is_active', 1)],

            'expires_at'         => ['nullable', 'date', 'after:now'],

            'required_skill_ids'   => ['nullable', 'array'],
            'required_skill_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('skills', 'id')->where('is_active', 1),
            ],

            'preferred_skill_ids'   => ['nullable', 'array'],
            'preferred_skill_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('skills', 'id')->where('is_active', 1),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $required  = $this->input('required_skill_ids', []) ?? [];
            $preferred = $this->input('preferred_skill_ids', []) ?? [];

            if (!empty(array_intersect($required, $preferred))) {
                $validator->errors()->add(
                    'preferred_skill_ids',
                    'Kỹ năng ưu tiên không được trùng với danh sách kỹ năng bắt buộc.'
                );
            }
        });
    }
}
