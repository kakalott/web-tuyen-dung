<?php

namespace App\Policies;

use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\Experience;
use App\Models\User;

class CandidateProfilePolicy
{
    /**
     * Xem hồ sơ ứng viên (API-CAN-01)
     */
    public function view(User $user, CandidateProfile $profile): bool
    {
        return $user->role === 'candidate' && $user->id === $profile->user_id;
    }

    /**
     * Cập nhật thông tin hồ sơ ứng viên (API-CAN-02)
     */
    public function update(User $user, CandidateProfile $profile): bool
    {
        return $user->role === 'candidate' && $user->id === $profile->user_id;
    }

    /**
     * Sửa hoặc xóa bản ghi Học vấn thuộc hồ sơ (API-CAN-04, API-CAN-05)
     */
    public function manageEducation(User $user, Education $education): bool
    {
        return $user->role === 'candidate'
            && $user->candidateProfile !== null
            && $education->candidate_profile_id === $user->candidateProfile->id;
    }

    /**
     * Sửa hoặc xóa bản ghi Kinh nghiệm thuộc hồ sơ (API-CAN-07, API-CAN-08)
     */
    public function manageExperience(User $user, Experience $experience): bool
    {
        return $user->role === 'candidate'
            && $user->candidateProfile !== null
            && $experience->candidate_profile_id === $user->candidateProfile->id;
    }
}
