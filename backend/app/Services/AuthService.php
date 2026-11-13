<?php

namespace App\Services;

use App\Models\User;
use App\Models\CandidateProfile;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthService
{
    /**
     * AUTH-001: Đăng ký tài khoản candidate hoặc company kèm hồ sơ trong 1 DB transaction.
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $email = strtolower(trim($data['email']));

            // Không mass-assignment role và account_status để chống leo thang đặc quyền
            $user = new User();
            $user->name = trim($data['name']);
            $user->email = $email;
            $user->password = Hash::make($data['password']);
            $user->role = $data['role']; // Đã được validate chỉ nhận 'candidate' | 'company'
            $user->account_status = 'active';
            $user->save();

            if ($user->role === 'candidate') {
                CandidateProfile::create([
                    'user_id' => $user->id,
                ]);
            } elseif ($user->role === 'company') {
                Company::create([
                    'user_id' => $user->id,
                    'company_name' => $user->name, // Khởi tạo từ name đăng ký
                    'verification_status' => 'unverified',
                ]);
            }

            return $user;
        });
    }

    /**
     * AUTH-002: Xác thực thông tin đăng nhập, kiểm tra khóa tài khoản và cấp phiên/token.
     */
    public function login(string $email, string $password): array
    {
        $normalizedEmail = strtolower(trim($email));
        $user = User::where('email', $normalizedEmail)->first();

        // Thông báo lỗi chung, không tiết lộ email có tồn tại hay không
        if (!$user || !Hash::check($password, $user->password)) {
            throw new AuthenticationException('Email hoặc mật khẩu không chính xác.');
        }

        // Chặn tài khoản đã bị admin khóa
        if ($user->account_status === 'locked') {
            throw new AccessDeniedHttpException('Tài khoản đã bị khóa.');
        }

        // Hỗ trợ cả SPA Session Cookie và Sanctum Token (tùy cách gọi từ client/test)
        if (request()->hasSession()) {
            Auth::guard('web')->login($user);
            request()->session()->regenerate();
        }

        return [
            'user' => [
                'id'   => $user->id,
                'role' => $user->role,
                'name' => $user->name,
            ],
            'authenticated' => true,
        ];
    }

    /**
     * AUTH-003: Kết thúc phiên hiện tại / thu hồi token.
     */
    public function logout(User $user): void
    {
        // Thu hồi Personal Access Token hiện tại nếu đang dùng Bearer token
        if ($user->currentAccessToken() && method_exists($user->currentAccessToken(), 'delete')) {
            $user->currentAccessToken()->delete();
        }

        // Hủy session nếu đang dùng SPA Cookie
        if (request()->hasSession()) {
            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }

    /**
     * AUTH-004: Eager load profile tương ứng với vai trò của người dùng hiện tại.
     */
    public function me(User $user): User
    {
        if ($user->role === 'candidate') {
            $user->loadMissing('candidateProfile');
        } elseif ($user->role === 'company') {
            $user->loadMissing('company');
        }

        return $user;
    }
}
