<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'company';
    }

    public function rules(): array
    {
        return [
            'scheduled_at'     => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'location_or_mode' => ['required', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.required'     => 'Thời gian phỏng vấn là bắt buộc.',
            'scheduled_at.date'         => 'Định dạng thời gian phỏng vấn không hợp lệ.',
            'scheduled_at.after'        => 'Thời gian phỏng vấn phải nằm ở tương lai.',
            'location_or_mode.required' => 'Địa điểm hoặc hình thức phỏng vấn là bắt buộc.',
            'location_or_mode.max'      => 'Địa điểm hoặc hình thức phỏng vấn không được vượt quá 255 ký tự.',
        ];
    }
}
