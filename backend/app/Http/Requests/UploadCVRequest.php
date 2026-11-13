<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCVRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            // Validate extension, MIME thực tế và size <= 5MB
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $file = $this->file('file');
            if ($file && $file->isValid()) {
                // Kiểm tra Magic Bytes %PDF-
                $handle = fopen($file->getRealPath(), 'rb');
                $header = fread($handle, 5);
                fclose($handle);

                if ($header !== '%PDF-') {
                    $validator->errors()->add('file', 'Tệp tin không đúng định dạng PDF hợp lệ.');
                }
            }
        });
    }
}
