<?php

namespace App\Policies;

use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function view(User $user, Job $job): bool
    {
        // Public hoặc của chính công ty hoặc admin
        return ($job->publication_status === 'active' && $job->review_status === 'approved')
            || ($job->company && $user->id === $job->company->user_id)
            || $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'company' && $user->company && $user->company->verification_status === 'verified';
    }

    public function update(User $user, Job $job): bool
    {
        return $job->company && $user->id === $job->company->user_id;
    }

    public function delete(User $user, Job $job): bool
    {
        return $job->company && $user->id === $job->company->user_id;
    }
}
