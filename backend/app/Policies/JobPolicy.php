<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    /**
     * Tạo tin tuyển dụng mới (API-JOB-04).
     * Bắt buộc tài khoản là 'company' và đã được Admin xác minh ('verified').
     */
    public function create(User $user): bool
    {
        return $user->role === 'company'
            && $user->company !== null
            && $user->company->verification_status === 'verified';
    }

    /**
     * Xem chi tiết tin trong không gian quản lý của Doanh nghiệp (API-JOB-05).
     */
    public function viewCompanyJob(User $user, Job $job): bool
    {
        return $user->role === 'company'
            && $user->company !== null
            && $job->company_id === $user->company->id;
    }

    /**
     * Chỉnh sửa tin tuyển dụng (API-JOB-06).
     * Yêu cầu đúng chủ sở hữu công ty và công ty đang ở trạng thái 'verified'.
     */
    public function update(User $user, Job $job): bool
    {
        return $user->role === 'company'
            && $user->company !== null
            && $user->company->verification_status === 'verified'
            && $job->company_id === $user->company->id;
    }

    /**
     * Đóng tin tuyển dụng đang hoạt động (API-JOB-07).
     */
    public function close(User $user, Job $job): bool
    {
        return $user->role === 'company'
            && $user->company !== null
            && $job->company_id === $user->company->id;
    }

    /**
     * Admin duyệt hoặc từ chối tin tuyển dụng (API-ADM-06).
     */
    public function review(User $user, Job $job): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Admin ẩn tin tuyển dụng vi phạm (API-ADM-07).
     */
    public function hide(User $user, Job $job): bool
    {
        return $user->role === 'admin';
    }
}
