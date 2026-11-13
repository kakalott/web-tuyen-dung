<?php

namespace App\Policies;

use App\Models\CandidateCV;
use App\Models\User;

class CandidateCVPolicy
{
    public function view(User $user, CandidateCV $cv): bool
    {
        return $cv->candidateProfile && $user->id === $cv->candidateProfile->user_id;
    }

    public function update(User $user, CandidateCV $cv): bool
    {
        return $cv->candidateProfile && $user->id === $cv->candidateProfile->user_id;
    }

    public function delete(User $user, CandidateCV $cv): bool
    {
        return $cv->candidateProfile && $user->id === $cv->candidateProfile->user_id;
    }
}
