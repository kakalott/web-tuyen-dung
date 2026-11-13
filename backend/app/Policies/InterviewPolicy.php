<?php

namespace App\Policies;

use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    public function view(User $user, Interview $interview): bool
    {
        $app = $interview->application;
        if (!$app) {
            return false;
        }

        $isCandidateOwner = $app->candidateProfile && $user->id === $app->candidateProfile->user_id;
        $isCompanyOwner = $app->job && $app->job->company && $user->id === $app->job->company->user_id;

        return $isCandidateOwner || $isCompanyOwner || $user->role === 'admin';
    }

    public function manage(User $user, Interview $interview): bool
    {
        $app = $interview->application;
        return $app && $app->job && $app->job->company && $user->id === $app->job->company->user_id;
    }
}
