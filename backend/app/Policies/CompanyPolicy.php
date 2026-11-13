<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    /**
     * Xem hồ sơ doanh nghiệp (API-COM-01)
     */
    public function view(User $user, Company $company): bool
    {
        return $user->role === 'company' && $user->id === $company->user_id;
    }

    /**
     * Cập nhật thông tin hồ sơ doanh nghiệp (API-COM-02)
     */
    public function update(User $user, Company $company): bool
    {
        return $user->role === 'company' && $user->id === $company->user_id;
    }

    /**
     * Kiểm tra doanh nghiệp đã được xác minh hay chưa
     * (Dùng cho các hành động yêu cầu Verified Company như đăng tin API-JOB-04, tải CV)
     */
    public function verified(User $user, Company $company): bool
    {
        return $user->role === 'company'
            && $user->id === $company->user_id
            && $company->verification_status === 'verified';
    }

    /**
     * Quyền Admin thực hiện xác minh doanh nghiệp (API-ADM-03)
     */
    public function verify(User $user, Company $company): bool
    {
        return $user->role === 'admin';
    }
}
