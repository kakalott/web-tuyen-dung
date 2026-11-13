<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'company';
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:200'],
            'description'  => ['nullable', 'string', 'max:10000'],
            'website_url'  => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required' => 'Tên doanh nghiệp không được để trống.',
            'company_name.max'      => 'Tên doanh nghiệp không được vượt quá 200 ký tự.',
            'website_url.url'       => 'Đường dẫn website không hợp lệ.',
            'website_url.max'       => 'Đường dẫn website không được vượt quá 255 ký tự.',
        ];
    }
}
