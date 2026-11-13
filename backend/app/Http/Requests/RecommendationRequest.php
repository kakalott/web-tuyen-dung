<?php

namespace App\Http\Requests;

use App\Models\CandidateCV;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class RecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'candidate';
    }

    public function rules(): array
    {
        return [
            'cv_id'     => ['required', 'integer'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $cvId = $this->input('cv_id');
            if (!$cvId) {
                return;
            }

            $user = $this->user();
            $profile = $user?->candidateProfile;

            $cv = CandidateCV::whereNull('deleted_at')->find($cvId);
            if (!$cv) {
                $validator->errors()->add('cv_id', 'CV không tồn tại.');
                return;
            }

            if (!$profile || (int) $cv->candidate_profile_id !== (int) $profile->id) {
                // IDOR: CV không thuộc về ứng viên đang đăng nhập
                throw new HttpResponseException(
                    response()->json([
                        'error' => [
                            'code'    => 'FORBIDDEN',
                            'message' => 'Bạn không có quyền truy cập CV này.',
                        ],
                        'request_id' => request()->header('X-Request-Id', 'req_' . bin2hex(random_bytes(8))),
                    ], 403)
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = (new ValidationException($validator))->errors();

        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'Dữ liệu không hợp lệ.',
                    'fields'  => $errors,
                ],
                'request_id' => request()->header('X-Request-Id', 'req_' . bin2hex(random_bytes(8))),
            ], 422)
        );
    }
}
