<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', 'string', Rule::in(['verified', 'unverified'])],
            'note'                => [
                'required_if:verification_status,unverified',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'verification_status.required' => 'Trạng thái xác minh là bắt buộc.',
            'verification_status.in'       => 'Trạng thái xác minh chỉ chấp nhận verified hoặc unverified.',
            'note.required_if'             => 'Vui lòng nhập ghi chú lý do khi từ chối hoặc hủy xác minh doanh nghiệp.',
        ];
    }
}
