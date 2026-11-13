<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyToJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->role === 'candidate'
            && $this->user()->candidateProfile !== null;
    }

    public function rules(): array
    {
        $candidateProfileId = $this->user()?->candidateProfile?->id;

        return [
            'cv_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('candidate_cvs', 'id')->where(function ($query) use ($candidateProfileId) {
                    $query->where('candidate_profile_id', $candidateProfileId);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'cv_id.required' => 'Vui lòng chọn CV để ứng tuyển.',
            'cv_id.exists'   => 'CV được chọn không tồn tại hoặc không thuộc quyền sở hữu của bạn.',
        ];
    }
}
