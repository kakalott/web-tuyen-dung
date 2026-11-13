<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Loại bỏ mọi tham số user_id do client cố tình truyền lậu.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('user_id');
        $this->query->remove('user_id');
    }

    public function rules(): array
    {
        return [
            'read_filter' => ['nullable', 'string', Rule::in(['all', 'read', 'unread'])],
            'page'        => ['nullable', 'integer', 'min:1'],
            'page_size'   => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'read_filter.in' => 'Bộ lọc trạng thái đọc chỉ chấp nhận: all, read hoặc unread.',
            'page_size.max'  => 'Số lượng bản ghi mỗi trang không được vượt quá 50.',
        ];
    }
}
