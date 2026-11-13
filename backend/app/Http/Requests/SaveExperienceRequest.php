<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            'employer_name' => ['required', 'string', 'max:190'],
            'job_title'     => ['required', 'string', 'max:150'],
            'start_date'    => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'      => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'description'   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
