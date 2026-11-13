<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\MeResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'data' => [
                'id'    => $user->id,
                'role'  => $user->role,
                'email' => $user->email,
            ]
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password')
        );

        return response()->json([
            'data' => $result
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'data' => [
                'success' => true
            ]
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authService->me($request->user());
        
        // Resource đã tự bọc key 'data' nên ta chỉ cần gọi new
        return response()->json([
            'data' => (new MeResource($user))->toArray($request)
        ]);
    }
}
