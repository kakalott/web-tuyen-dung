<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // API đăng ký công khai
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            // Cấm tuyệt đối đăng ký role = 'admin' qua API công khai
            'role'     => ['required', 'string', Rule::in(['candidate', 'company'])],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Vai trò đăng ký không hợp lệ. Chỉ chấp nhận candidate hoặc company.',
        ];
    }
}
