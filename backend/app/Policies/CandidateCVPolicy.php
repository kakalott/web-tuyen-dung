<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CandidateCV;

class CandidateCVPolicy
{
    /**
     * Xác thực quyền xem, tải xuống hoặc xác nhận (API-CV-03 đến API-CV-07)
     */
    public function view(User $user, CandidateCV $cv): bool
    {
        return $user->role === 'candidate' 
            && $user->candidateProfile !== null 
            && $cv->candidate_profile_id === $user->candidateProfile->id;
    }

    public function update(User $user, CandidateCV $cv): bool
    {
        return $this->view($user, $cv);
    }
}
