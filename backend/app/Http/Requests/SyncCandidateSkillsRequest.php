<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCandidateSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            'skill_ids'   => ['present', 'array'],
            'skill_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('skills', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
        ];
    }
}
