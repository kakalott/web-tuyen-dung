<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'company';
    }

    public function rules(): array
    {
        return [
            'new_status' => [
                'required',
                'string',
                Rule::in(['reviewing', 'interview', 'passed', 'rejected']),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_status.required' => 'Vui lòng chọn trạng thái mới cho hồ sơ ứng tuyển.',
            'new_status.in'       => 'Trạng thái mới không hợp lệ. Chỉ chấp nhận: reviewing, interview, passed, rejected.',
        ];
    }
}
