<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status'    => [
                'nullable',
                'string',
                Rule::in(['pending', 'reviewing', 'interview', 'passed', 'rejected', 'withdrawn']),
            ],
            'sort'      => [
                'nullable',
                'string',
                Rule::in(['applied_desc', 'match_desc']),
            ],
            'page'      => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
