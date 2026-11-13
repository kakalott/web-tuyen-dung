<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatalogItemRequest extends FormRequest
{
    /**
     * Chỉ Admin đang hoạt động mới có quyền thao tác danh mục hệ thống.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === 'admin'
            && ($user->account_status ?? 'active') === 'active';
    }

    /**
     * Chuẩn hóa khoảng trắng và loại bỏ các trường hệ thống không được phép gán từ client.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('id');
        $this->request->remove('normalized_name');
        $this->request->remove('normalized_alias');

        $sanitized = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $sanitized['name'] = preg_replace('/\s+/u', ' ', trim($this->input('name')));
        }

        if ($this->has('alias_name') && is_string($this->input('alias_name'))) {
            $sanitized['alias_name'] = preg_replace('/\s+/u', ' ', trim($this->input('alias_name')));
        }

        if (!empty($sanitized)) {
            $this->merge($sanitized);
        }
    }

    /**
     * Quy tắc kiểm tra dữ liệu cho cả POST (tạo mới) và PATCH (cập nhật tên / ẩn-kích hoạt).
     */
    public function rules(): array
    {
        $isAliasRoute = str_contains($this->path(), '/aliases');
        $isCreate     = $this->isMethod('POST');

        if ($isAliasRoute) {
            return [
                'alias_name' => [
                    $isCreate ? 'required' : 'sometimes',
                    'string',
                    'min:1',
                    'max:120',
                ],
                'is_active' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'name' => [
                $isCreate ? 'required' : 'sometimes',
                'string',
                'min:1',
                'max:120',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'Tên mục danh mục không được để trống.',
            'name.max'            => 'Tên mục danh mục không được vượt quá 120 ký tự.',
            'alias_name.required' => 'Tên biến thể (alias) không được để trống.',
            'alias_name.max'      => 'Tên biến thể (alias) không được vượt quá 120 ký tự.',
            'is_active.boolean'   => 'Trạng thái kích hoạt (is_active) phải là kiểu boolean.',
        ];
    }
}
