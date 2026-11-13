<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Job;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Ứng viên xem chi tiết đơn ứng tuyển của chính mình (API-APP-03).
     */
    public function viewCandidateApplication(User $user, Application $application): bool
    {
        return $user->role === 'candidate'
            && $user->candidateProfile !== null
            && $application->candidate_profile_id === $user->candidateProfile->id;
    }

    /**
     * Ứng viên rút hồ sơ ứng tuyển của chính mình (API-APP-04).
     */
    public function withdraw(User $user, Application $application): bool
    {
        return $user->role === 'candidate'
            && $user->candidateProfile !== null
            && $application->candidate_profile_id === $user->candidateProfile->id;
    }

    /**
     * Doanh nghiệp đã xác minh xem danh sách hồ sơ nộp vào tin của mình (API-APP-05).
     */
    public function viewJobApplications(User $user, Job $job): bool
    {
        return $this->isVerifiedCompanyOwner($user, $job);
    }

    /**
     * Doanh nghiệp đã xác minh xem chi tiết đơn ứng tuyển thuộc tin của mình (API-APP-06).
     */
    public function viewCompanyApplication(User $user, Application $application): bool
    {
        return $application->job !== null
            && $this->isVerifiedCompanyOwner($user, $application->job);
    }

    /**
     * Doanh nghiệp đã xác minh tải/xem CV của ứng viên qua stream riêng tư (API-APP-07).
     * Thực thi chuỗi quyền company -> job -> application -> cv (BR-SEC-02).
     */
    public function downloadCv(User $user, Application $application): bool
    {
        return $application->job !== null
            && $application->cv_id !== null
            && $this->isVerifiedCompanyOwner($user, $application->job);
    }

    /**
     * Doanh nghiệp đã xác minh cập nhật trạng thái đơn ứng tuyển (API-APP-08).
     */
    public function updateStatus(User $user, Application $application): bool
    {
        return $application->job !== null
            && $this->isVerifiedCompanyOwner($user, $application->job);
    }

    /**
     * Helper kiểm tra đồng thời 3 điều kiện: role company, đã verified và đúng chủ sở hữu Job.
     */
    private function isVerifiedCompanyOwner(User $user, Job $job): bool
    {
        return $user->role === 'company'
            && $user->company !== null
            && $user->company->verification_status === 'verified'
            && $job->company_id === $user->company->id;
    }
}
