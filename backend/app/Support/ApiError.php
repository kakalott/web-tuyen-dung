<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use App\Exceptions\DomainConflictException;
use App\Exceptions\AIServiceException;
use Throwable;

class ApiError
{
    public static function render($request, Throwable $exception)
    {
        $code = 'INTERNAL_ERROR';
        $message = 'Lỗi hệ thống nội bộ.';
        $fields = [];
        $statusCode = 500;

        if ($exception instanceof AuthenticationException) {
            $code = 'AUTH_REQUIRED';
            $message = 'Bạn cần đăng nhập để thực hiện thao tác này.';
            $statusCode = 401;
        } elseif ($exception instanceof AuthorizationException || $exception instanceof AccessDeniedHttpException) {
            $code = 'FORBIDDEN';
            $message = 'Bạn không có quyền truy cập tài nguyên này.';
            $statusCode = 403;
        } elseif ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            $code = 'RESOURCE_NOT_FOUND';
            $message = 'Tài nguyên không tồn tại hoặc đã bị xóa.';
            $statusCode = 404;
        } elseif ($exception instanceof ValidationException) {
            $code = 'VALIDATION_ERROR';
            $message = 'Dữ liệu đầu vào không hợp lệ.';
            $fields = $exception->errors();
            $statusCode = 422;
        } elseif ($exception instanceof DomainConflictException) {
            $code = 'STATE_CONFLICT';
            $message = $exception->getMessage();
            $statusCode = 409;
        } elseif ($exception instanceof AIServiceException) {
            $code = 'AI_SERVICE_ERROR';
            $message = $exception->getMessage();
            $statusCode = 502;
        }

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => config('app.debug') && $statusCode == 500 ? $exception->getMessage() : $message,
                'fields' => empty($fields) ? (object)[] : $fields,
            ],
            'request_id' => 'req_' . Str::uuid()->toString(),
        ], $statusCode);
    }
}
