<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    /**
     * Helper kiểm tra doanh nghiệp đã xác minh và sở hữu đơn ứng tuyển.
     */
    private function isVerifiedApplicationOwner(User $user, Application $application): bool
    {
        return $user->role === 'company'
            && $user->company !== null
            && $user->company->verification_status === 'verified'
            && $application->job !== null
            && $application->job->company_id === $user->company->id;
    }

    /**
     * Xem danh sách lịch phỏng vấn của một đơn ứng tuyển.
     */
    public function viewAny(User $user, Application $application): bool
    {
        return $this->isVerifiedApplicationOwner($user, $application);
    }

    /**
     * Xem chi tiết lịch phỏng vấn.
     */
    public function view(User $user, Interview $interview): bool
    {
        $app = $interview->application;
        if (!$app) {
            return false;
        }

        $isCandidateOwner = $app->candidateProfile && $user->id === $app->candidateProfile->user_id;
        $isCompanyOwner = $this->isVerifiedApplicationOwner($user, $app);

        return $isCandidateOwner || $isCompanyOwner || $user->role === 'admin';
    }

    /**
     * Tạo lịch phỏng vấn mới cho đơn ứng tuyển (API-INT-01).
     */
    public function create(User $user, Application $application): bool
    {
        return $this->isVerifiedApplicationOwner($user, $application);
    }

    /**
     * Chỉnh sửa thông tin lịch phỏng vấn (API-INT-02).
     */
    public function update(User $user, Interview $interview): bool
    {
        return $interview->application !== null
            && $this->isVerifiedApplicationOwner($user, $interview->application);
    }

    /**
     * Hủy lịch phỏng vấn (API-INT-03).
     */
    public function cancel(User $user, Interview $interview): bool
    {
        return $interview->application !== null
            && $this->isVerifiedApplicationOwner($user, $interview->application);
    }

    /**
     * Đánh dấu hoàn thành buổi phỏng vấn (API-INT-04).
     */
    public function complete(User $user, Interview $interview): bool
    {
        return $interview->application !== null
            && $this->isVerifiedApplicationOwner($user, $interview->application);
    }

    /**
     * Đánh dấu ứng viên vắng mặt (API-INT-05).
     */
    public function noShow(User $user, Interview $interview): bool
    {
        return $interview->application !== null
            && $this->isVerifiedApplicationOwner($user, $interview->application);
    }
}
