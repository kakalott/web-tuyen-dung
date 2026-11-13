<?php

namespace App\Policies;

use App\Models\CandidateProfile;
use App\Models\User;

class CandidateProfilePolicy
{
    public function view(User $user, CandidateProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->role === 'admin';
    }

    public function update(User $user, CandidateProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }
}
