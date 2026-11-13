<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->id === $company->user_id;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->id === $company->user_id;
    }

    // Trạng thái verified bắt buộc phải kiểm tra ở Backend
    public function verified(User $user, Company $company): bool
    {
        return $user->role === 'company' 
            && $user->id === $company->user_id 
            && $company->verification_status === 'verified';
    }
}
