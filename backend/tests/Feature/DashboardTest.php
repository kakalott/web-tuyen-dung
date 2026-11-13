<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-ADM-03: Dashboard Admin trả chính xác 4 chỉ số tổng hợp từ MySQL và chặn Non-Admin.
     */
    public function test_admin_dashboard_returns_exact_mysql_counts_and_enforces_rbac(): void
    {
        $admin = User::factory()->create([
            'role'           => 'admin',
            'account_status' => 'active',
        ]);

        $jg = JobGroup::firstOrCreate(['name' => 'IT Software'], ['is_active' => true]);
        $et = EmploymentType::firstOrCreate(['name' => 'Full-time'], ['is_active' => true]);
        $wm = WorkMode::firstOrCreate(['name' => 'Hybrid'], ['is_active' => true]);
        $sl = SeniorityLevel::firstOrCreate(['name' => 'Middle'], ['is_active' => true]);

        // Tạo 2 doanh nghiệp: 1 unverified, 1 verified
        $companyUser1 = User::factory()->create(['role' => 'company', 'account_status' => 'active']);
        $companyUnverified = new Company();
        $companyUnverified->user_id             = $companyUser1->id;
        $companyUnverified->company_name        = 'Unverified Corp';
        $companyUnverified->verification_status = 'unverified';
        $companyUnverified->save();

        $companyUser2 = User::factory()->create(['role' => 'company', 'account_status' => 'active']);
        $companyVerified = new Company();
        $companyVerified->user_id             = $companyUser2->id;
        $companyVerified->company_name        = 'Verified Corp';
        $companyVerified->verification_status = 'verified';
        $companyVerified->verified_by         = $admin->id;
        $companyVerified->verified_at         = now();
        $companyVerified->save();

        // Tạo 3 tin tuyển dụng: 2 pending, 1 approved
        $jobPending1 = new Job();
        $jobPending1->company_id         = $companyVerified->id;
        $jobPending1->job_group_id       = $jg->id;
        $jobPending1->employment_type_id = $et->id;
        $jobPending1->work_mode_id       = $wm->id;
        $jobPending1->seniority_level_id = $sl->id;
        $jobPending1->title              = 'Backend Developer';
        $jobPending1->description        = 'Laravel & MySQL';
        $jobPending1->requirements_text  = '2 years experience';
        $jobPending1->review_status      = 'pending';
        $jobPending1->publication_status = 'not_public';
        $jobPending1->save();

        $jobPending2 = new Job();
        $jobPending2->company_id         = $companyVerified->id;
        $jobPending2->job_group_id       = $jg->id;
        $jobPending2->employment_type_id = $et->id;
        $jobPending2->work_mode_id       = $wm->id;
        $jobPending2->seniority_level_id = $sl->id;
        $jobPending2->title              = 'Frontend Developer';
        $jobPending2->description        = 'Vue 3';
        $jobPending2->requirements_text  = '1 year experience';
        $jobPending2->review_status      = 'pending';
        $jobPending2->publication_status = 'not_public';
        $jobPending2->save();

        $jobApproved = new Job();
        $jobApproved->company_id         = $companyVerified->id;
        $jobApproved->job_group_id       = $jg->id;
        $jobApproved->employment_type_id = $et->id;
        $jobApproved->work_mode_id       = $wm->id;
        $jobApproved->seniority_level_id = $sl->id;
        $jobApproved->title              = 'AI Engineer';
        $jobApproved->description        = 'Python & NLP';
        $jobApproved->requirements_text  = 'FastAPI';
        $jobApproved->review_status      = 'approved';
        $jobApproved->publication_status = 'active';
        $jobApproved->save();

        // Tạo 1 ứng viên, 1 CV và 1 đơn ứng tuyển
        $candidateUser = User::factory()->create(['role' => 'candidate', 'account_status' => 'active']);
        $profile = CandidateProfile::create([
            'user_id'   => $candidateUser->id,
            'full_name' => 'Nguyen Van A',
        ]);

        $cv = new CandidateCv();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'cv.pdf';
        $cv->storage_key          = 'private_cvs/sample-' . uniqid() . '.pdf';
        $cv->mime_type            = 'application/pdf';
        $cv->file_size            = 10240;
        $cv->sha256               = hash('sha256', 'dummy-content-' . uniqid());
        $cv->uploaded_at          = now();
        $cv->save();

        $application = new Application();
        $application->job_id               = $jobApproved->id;
        $application->candidate_profile_id = $profile->id;
        $application->cv_id                = $cv->id;
        $application->status               = 'pending';
        $application->applied_at           = now();
        $application->save();

        // Tổng users = 1 admin + 2 company + 1 candidate = 4
        // companies_unverified = 1
        // jobs_pending = 2
        // applications_total = 1
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'users_total'          => 4,
                    'companies_unverified' => 1,
                    'jobs_pending'         => 2,
                    'applications_total'   => 1,
                ],
            ]);

        // Guest -> 401
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);

        // Non-Admin (Candidate & Company) -> 403
        $this->actingAs($candidateUser)->getJson('/api/v1/admin/dashboard')->assertStatus(403);
        $this->actingAs($companyUser1)->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }
}
