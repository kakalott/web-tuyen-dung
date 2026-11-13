<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        // Ứng viên nộp hoặc Nhà tuyển dụng sở hữu Job hoặc Admin
        $isCandidateOwner = $application->candidateProfile && $user->id === $application->candidateProfile->user_id;
        $isCompanyOwner = $application->job && $application->job->company && $user->id === $application->job->company->user_id;

        return $isCandidateOwner || $isCompanyOwner || $user->role === 'admin';
    }

    public function withdraw(User $user, Application $application): bool
    {
        return $application->candidateProfile && $user->id === $application->candidateProfile->user_id;
    }

    public function updateStatus(User $user, Application $application): bool
    {
        return $application->job && $application->job->company && $user->id === $application->job->company->user_id;
    }
}
