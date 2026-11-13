<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmCVExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            'confirmed_text'         => ['required', 'string'],
            'confirmed_skill_ids'    => ['present', 'array'],
            'confirmed_skill_ids.*'  => [
                'integer',
                'distinct',
                Rule::exists('skills', 'id')->where('is_active', 1),
            ],
            'rejected_skill_ids'     => ['nullable', 'array'],
            'rejected_skill_ids.*'   => ['integer', 'distinct'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $confirmed = $this->input('confirmed_skill_ids', []);
            $rejected = $this->input('rejected_skill_ids', []);
            
            // Đảm bảo tập kỹ năng xác nhận và từ chối không giao nhau (disjoint)
            if (!empty(array_intersect($confirmed, $rejected))) {
                $validator->errors()->add('rejected_skill_ids', 'Kỹ năng từ chối không được trùng với kỹ năng đã xác nhận.');
            }
        });
    }
}
