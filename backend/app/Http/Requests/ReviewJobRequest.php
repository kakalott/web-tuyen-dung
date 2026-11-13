<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        // Phân biệt endpoint /hide và /review qua tên route hoặc URI
        if ($this->routeIs('*.hide') || str_ends_with($this->path(), '/hide')) {
            return [
                'reason' => ['required', 'string', 'min:1', 'max:1000'],
            ];
        }

        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'reason'   => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $this->input('decision') === 'reject'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.in'       => 'Quyết định kiểm duyệt chỉ chấp nhận approve hoặc reject.',
            'reason.required'   => 'Vui lòng nhập lý do khi từ chối hoặc ẩn tin tuyển dụng.',
            'reason.required_if'=> 'Bắt buộc nhập lý do khi từ chối tin tuyển dụng.',
        ];
    }
}
