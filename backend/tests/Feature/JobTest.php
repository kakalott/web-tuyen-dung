<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $user = new User();
        $user->name = 'System Admin';
        $user->email = 'admin@system.local';
        $user->password = bcrypt('Password123!');
        $user->role = 'admin';
        $user->account_status = 'active';
        $user->save();

        return $user;
    }

    private function createCompanyActor(string $email, string $verificationStatus = 'verified'): array
    {
        $user = new User();
        $user->name = 'HR Manager';
        $user->email = $email;
        $user->password = bcrypt('Password123!');
        $user->role = 'company';
        $user->account_status = 'active';
        $user->save();

        $company = new Company();
        $company->user_id = $user->id;
        $company->company_name = 'Tech Company ' . $user->id;
        $company->description = 'Software engineering company';
        $company->website_url = 'https://example.com';
        $company->verification_status = $verificationStatus;
        $company->verified_at = $verificationStatus === 'verified' ? now() : null;
        $company->save();

        return [$user, $company];
    }

    private function seedCatalogFixtures(): array
    {
        $jobGroup = JobGroup::create(['name' => 'Software Development', 'is_active' => true]);
        $empType  = EmploymentType::create(['name' => 'Full-time', 'is_active' => true]);
        $workMode = WorkMode::create(['name' => 'Hybrid', 'is_active' => true]);
        $seniority = SeniorityLevel::create(['name' => 'Middle', 'is_active' => true]);
        $inactiveWorkMode = WorkMode::create(['name' => 'Legacy Mode', 'is_active' => false]);

        $skillReq = Skill::create([
            'name'            => 'Laravel',
            'normalized_name' => 'laravel',
            'is_active'       => true,
        ]);

        $skillPref = Skill::create([
            'name'            => 'Vue.js',
            'normalized_name' => 'vue.js',
            'is_active'       => true,
        ]);

        return compact('jobGroup', 'empType', 'workMode', 'seniority', 'inactiveWorkMode', 'skillReq', 'skillPref');
    }

    private function validJobPayload(array $cat, array $overrides = []): array
    {
        return array_merge([
            'title'               => 'Senior Backend Laravel Engineer',
            'description'         => 'Build scalable RESTful APIs and AI recruitment microservices.',
            'requirements_text'   => '3+ years with PHP/Laravel and MySQL.',
            'job_group_id'        => $cat['jobGroup']->id,
            'employment_type_id'  => $cat['empType']->id,
            'work_mode_id'        => $cat['workMode']->id,
            'seniority_level_id'  => $cat['seniority']->id,
            'expires_at'          => now()->addDays(14)->toIso8601String(),
            'required_skill_ids'  => [$cat['skillReq']->id],
            'preferred_skill_ids' => [$cat['skillPref']->id],
        ], $overrides);
    }

    /**
     * TC-JOB-01: Unverified company is blocked from creating jobs (403 FORBIDDEN).
     */
    public function test_unverified_company_cannot_create_job(): void
    {
        $cat = $this->seedCatalogFixtures();
        [$unverifiedUser] = $this->createCompanyActor('unverified@company.com', 'unverified');

        Sanctum::actingAs($unverifiedUser);

        $response = $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat));

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields'], 'request_id']);
    }

    /**
     * TC-JOB-02: Verified company creates job (pending/not_public) & validates skill overlap / inactive lookup.
     */
    public function test_verified_company_creates_job_and_validates_inputs(): void
    {
        $cat = $this->seedCatalogFixtures();
        [$companyUser, $company] = $this->createCompanyActor('verified@company.com', 'verified');

        Sanctum::actingAs($companyUser);

        // 1. Overlapping required & preferred skills -> 422
        $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat, [
            'required_skill_ids'  => [$cat['skillReq']->id],
            'preferred_skill_ids' => [$cat['skillReq']->id],
        ]))->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 2. Inactive work_mode_id -> 422
        $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat, [
            'work_mode_id' => $cat['inactiveWorkMode']->id,
        ]))->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 3. Valid payload -> 201 with pending / not_public
        $res = $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat));

        $res->assertStatus(201)
            ->assertJsonPath('data.review_status', 'pending')
            ->assertJsonPath('data.publication_status', 'not_public')
            ->assertJsonCount(2, 'data.skills');

        $jobId = $res->json('data.id');

        $this->assertDatabaseHas('jobs', [
            'id'                 => $jobId,
            'company_id'         => $company->id,
            'review_status'      => 'pending',
            'publication_status' => 'not_public',
        ]);

        $this->assertDatabaseHas('job_skills', [
            'job_id'           => $jobId,
            'skill_id'         => $cat['skillReq']->id,
            'requirement_type' => 'required',
        ]);

        $this->assertDatabaseHas('job_skills', [
            'job_id'           => $jobId,
            'skill_id'         => $cat['skillPref']->id,
            'requirement_type' => 'preferred',
        ]);
    }

    /**
     * TC-JOB-03 & TC-ADM-02: Admin moderation lifecycle (reject requires reason, approve -> active, hide -> hidden).
     */
    public function test_admin_moderation_approve_reject_and_hide_lifecycle(): void
    {
        $cat = $this->seedCatalogFixtures();
        [$companyUser, $company] = $this->createCompanyActor('hr@company.com', 'verified');
        $admin = $this->createAdmin();

        Sanctum::actingAs($companyUser);
        $jobAId = $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat, ['title' => 'Job Reject Test']))
            ->json('data.id');
        $jobBId = $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat, ['title' => 'Job Approve & Hide Test']))
            ->json('data.id');

        Sanctum::actingAs($admin);

        // 1. Admin lists pending jobs (API-ADM-04) & views detail (API-ADM-05)
        $this->getJson('/api/v1/admin/jobs/pending')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 2);

        $this->getJson("/api/v1/admin/jobs/{$jobAId}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $jobAId);

        // 2. Reject without reason -> 422
        $this->patchJson("/api/v1/admin/jobs/{$jobAId}/review", [
            'decision' => 'reject',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 3. Reject with reason -> rejected / not_public + notification
        $this->patchJson("/api/v1/admin/jobs/{$jobAId}/review", [
            'decision' => 'reject',
            'reason'   => 'Mô tả công việc thiếu thông tin yêu cầu chuyên môn.',
        ])->assertStatus(200)
            ->assertJsonPath('data.review_status', 'rejected')
            ->assertJsonPath('data.publication_status', 'not_public')
            ->assertJsonPath('data.rejection_reason', 'Mô tả công việc thiếu thông tin yêu cầu chuyên môn.');

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $companyUser->id,
            'event_type'   => 'job.rejected',
            'related_type' => 'job',
            'related_id'   => $jobAId,
        ]);

        // 4. Approve job B -> approved / active + notification
        $this->patchJson("/api/v1/admin/jobs/{$jobBId}/review", [
            'decision' => 'approve',
        ])->assertStatus(200)
            ->assertJsonPath('data.review_status', 'approved')
            ->assertJsonPath('data.publication_status', 'active');

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $companyUser->id,
            'event_type'   => 'job.approved',
            'related_type' => 'job',
            'related_id'   => $jobBId,
        ]);

        // 5. Hide job B -> hidden + hidden_reason + notification
        $this->patchJson("/api/v1/admin/jobs/{$jobBId}/hide", [
            'reason' => 'Tin tuyển dụng vi phạm chính sách nội dung.',
        ])->assertStatus(200)
            ->assertJsonPath('data.publication_status', 'hidden')
            ->assertJsonPath('data.hidden_reason', 'Tin tuyển dụng vi phạm chính sách nội dung.');

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $companyUser->id,
            'event_type'   => 'job.hidden',
            'related_type' => 'job',
            'related_id'   => $jobBId,
        ]);
    }

    /**
     * TC-JOB-04: Editing main content fields of an approved job resets it to pending / not_public.
     */
    public function test_editing_main_fields_of_approved_job_resets_to_pending(): void
    {
        $cat = $this->seedCatalogFixtures();
        [$companyUser] = $this->createCompanyActor('hr@company.com', 'verified');
        $admin = $this->createAdmin();

        Sanctum::actingAs($companyUser);
        $jobId = $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat))->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/jobs/{$jobId}/review", ['decision' => 'approve'])->assertStatus(200);

        // Verify job is publicly visible before edit
        $this->getJson("/api/v1/jobs/{$jobId}")->assertStatus(200);

        // Company edits title / description -> automatically resets to pending / not_public
        Sanctum::actingAs($companyUser);
        $updateRes = $this->patchJson("/api/v1/company/jobs/{$jobId}", $this->validJobPayload($cat, [
            'title' => 'Updated Principal Laravel Architect',
        ]));

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.review_status', 'pending')
            ->assertJsonPath('data.publication_status', 'not_public');

        // Public detail must immediately return 404 after re-review reset
        $this->getJson("/api/v1/jobs/{$jobId}")->assertStatus(404);
    }

    /**
     * TC-JOB-05 & TC-JOB-06: Public search & detail only expose approved + active + non-expired jobs.
     */
    public function test_public_search_and_detail_enforce_strict_visibility_rules(): void
    {
        $cat = $this->seedCatalogFixtures();
        [, $company] = $this->createCompanyActor('hr@company.com', 'verified');

        $baseAttrs = [
            'company_id'         => $company->id,
            'description'        => 'Backend role description',
            'requirements_text'  => 'PHP & MySQL',
            'job_group_id'       => $cat['jobGroup']->id,
            'employment_type_id' => $cat['empType']->id,
            'work_mode_id'       => $cat['workMode']->id,
            'seniority_level_id' => $cat['seniority']->id,
        ];

        $createJob = function (array $attrs): Job {
            $j = new Job();
            foreach ($attrs as $k => $v) {
                $j->{$k} = $v;
            }
            $j->save();
            return $j;
        };

        // 5 jobs that MUST NOT be public
        $pendingJob = $createJob(array_merge($baseAttrs, [
            'title' => 'Pending Job', 'review_status' => 'pending', 'publication_status' => 'not_public', 'expires_at' => now()->addDays(5),
        ]));
        $rejectedJob = $createJob(array_merge($baseAttrs, [
            'title' => 'Rejected Job', 'review_status' => 'rejected', 'publication_status' => 'not_public', 'rejection_reason' => 'Secret reject note', 'expires_at' => now()->addDays(5),
        ]));
        $closedJob = $createJob(array_merge($baseAttrs, [
            'title' => 'Closed Job', 'review_status' => 'approved', 'publication_status' => 'closed', 'expires_at' => now()->addDays(5),
        ]));
        $hiddenJob = $createJob(array_merge($baseAttrs, [
            'title' => 'Hidden Job', 'review_status' => 'approved', 'publication_status' => 'hidden', 'hidden_reason' => 'Secret hidden note', 'expires_at' => now()->addDays(5),
        ]));
        $expiredJob = $createJob(array_merge($baseAttrs, [
            'title' => 'Expired Job', 'review_status' => 'approved', 'publication_status' => 'active', 'expires_at' => now()->subDay(),
        ]));

        // 1 valid public job
        $publicJob = $createJob(array_merge($baseAttrs, [
            'title' => 'Valid Public Laravel Job', 'review_status' => 'approved', 'publication_status' => 'active', 'expires_at' => now()->addDay(),
        ]));

        // 1. Public list (API-JOB-01) returns ONLY the 1 valid job
        $listRes = $this->getJson('/api/v1/jobs?keyword=Job');
        $listRes->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $publicJob->id)
            ->assertJsonMissingPath('data.items.0.rejection_reason')
            ->assertJsonMissingPath('data.items.0.hidden_reason');

        // 2. Public detail (API-JOB-02) returns 200 for valid job & 404 for all 5 non-public jobs
        $this->getJson("/api/v1/jobs/{$publicJob->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $publicJob->id)
            ->assertJsonMissingPath('data.rejection_reason')
            ->assertJsonMissingPath('data.hidden_reason');

        foreach ([$pendingJob, $rejectedJob, $closedJob, $hiddenJob, $expiredJob] as $hiddenItem) {
            $this->getJson("/api/v1/jobs/{$hiddenItem->id}")->assertStatus(404);
        }

        // 3. Public catalog lookups (API-CAT-06) returns only active items
        $this->getJson('/api/v1/catalog/lookups')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.work_modes')
            ->assertJsonPath('data.work_modes.0.id', $cat['workMode']->id);
    }

    /**
     * TC-JOB-07 & TC-JOB-08: Company closes active job & cross-company IDOR is blocked.
     */
    public function test_close_job_and_cross_company_idor_protection(): void
    {
        $cat = $this->seedCatalogFixtures();
        [$companyAUser] = $this->createCompanyActor('comp_a@company.com', 'verified');
        [$companyBUser] = $this->createCompanyActor('comp_b@company.com', 'verified');
        $admin = $this->createAdmin();

        Sanctum::actingAs($companyAUser);
        $jobAId = $this->postJson('/api/v1/company/jobs', $this->validJobPayload($cat))->json('data.id');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/jobs/{$jobAId}/review", ['decision' => 'approve'])->assertStatus(200);

        // Company B attempts IDOR view, update, and close on Company A's job -> 403 FORBIDDEN
        Sanctum::actingAs($companyBUser);
        $this->getJson("/api/v1/company/jobs/{$jobAId}")->assertStatus(403);
        $this->patchJson("/api/v1/company/jobs/{$jobAId}", $this->validJobPayload($cat))->assertStatus(403);
        $this->postJson("/api/v1/company/jobs/{$jobAId}/close")->assertStatus(403);

        // Company A closes its active job -> 200 closed
        Sanctum::actingAs($companyAUser);
        $this->postJson("/api/v1/company/jobs/{$jobAId}/close")
            ->assertStatus(200)
            ->assertJsonPath('data.review_status', 'approved')
            ->assertJsonPath('data.publication_status', 'closed');

        // Closing again -> 409 STATE_CONFLICT
        $this->postJson("/api/v1/company/jobs/{$jobAId}/close")->assertStatus(409);

        // Closed job is removed from public search & detail
        $this->getJson('/api/v1/jobs')->assertJsonPath('data.pagination.total', 0);
        $this->getJson("/api/v1/jobs/{$jobAId}")->assertStatus(404);
    }
}
