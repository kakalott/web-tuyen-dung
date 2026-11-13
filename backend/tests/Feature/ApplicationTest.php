<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateCV;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CVExtraction;
use App\Models\EmploymentType;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function createLookupIds(): array
    {
        $jg = JobGroup::firstOrCreate(['name' => 'IT Software'], ['is_active' => true]);
        $et = EmploymentType::firstOrCreate(['name' => 'Full-time'], ['is_active' => true]);
        $wm = WorkMode::firstOrCreate(['name' => 'Onsite'], ['is_active' => true]);
        $sl = SeniorityLevel::firstOrCreate(['name' => 'Junior'], ['is_active' => true]);

        return [
            'job_group_id'       => $jg->id,
            'employment_type_id' => $et->id,
            'work_mode_id'       => $wm->id,
            'seniority_level_id' => $sl->id,
        ];
    }

    private function createVerifiedCompanyWithJob(array $jobOverrides = []): array
    {
        $companyUser = new User();
        $companyUser->name = 'HR Rep';
        $companyUser->email = 'comp_' . uniqid() . '@example.com';
        $companyUser->password = bcrypt('Password123!');
        $companyUser->role = 'company';
        $companyUser->account_status = 'active';
        $companyUser->save();

        $company = new Company();
        $company->user_id = $companyUser->id;
        $company->company_name = 'Acme Tech Corp';
        $company->verification_status = 'verified';
        $company->save();

        $lookups = $this->createLookupIds();
        $jobData = array_merge([
            'company_id'         => $company->id,
            'title'              => 'Backend Laravel Developer',
            'description'        => 'Build scalable APIs.',
            'requirements_text'  => 'PHP, Laravel, MySQL.',
            'review_status'      => 'approved',
            'publication_status' => 'active',
            'expires_at'         => now()->addDays(15),
        ], $lookups, $jobOverrides);

        $job = new Job();
        foreach ($jobData as $k => $v) {
            $job->{$k} = $v;
        }
        $job->save();

        return [$companyUser, $company, $job];
    }

    private function createCandidateWithCV(string $reviewStatus = 'confirmed'): array
    {
        $candidateUser = new User();
        $candidateUser->name = 'Candidate A';
        $candidateUser->email = 'cand_' . uniqid() . '@example.com';
        $candidateUser->password = bcrypt('Password123!');
        $candidateUser->role = 'candidate';
        $candidateUser->account_status = 'active';
        $candidateUser->save();

        $profile = CandidateProfile::create(['user_id' => $candidateUser->id]);

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'resume.pdf';
        $cv->storage_key          = 'private/2026/11/secret-uuid.pdf';
        $cv->file_size            = 102400;
        $cv->mime_type            = 'application/pdf';
        $cv->sha256               = hash('sha256', 'dummy');
        $cv->parse_status         = 'processed';
        $cv->uploaded_at          = now();
        $cv->save();

        CVExtraction::create([
            'cv_id'          => $cv->id,
            'extracted_text' => 'Experienced PHP Laravel Developer',
            'review_status'  => $reviewStatus,
            'confirmed_at'   => $reviewStatus === 'confirmed' ? now() : null,
        ]);

        return [$candidateUser, $profile, $cv];
    }

    public function test_apply_happy_path_and_candidate_tracking(): void
    {
        [$companyUser, $company, $job] = $this->createVerifiedCompanyWithJob();
        [$candidateUser, $profile, $cv] = $this->createCandidateWithCV('confirmed');

        // TC-APP-01: Nộp hồ sơ thành công
        $res = $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$job->id}/applications", ['cv_id' => $cv->id]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $appId = $res->json('data.application_id');

        $this->assertDatabaseHas('applications', [
            'id'                   => $appId,
            'candidate_profile_id' => $profile->id,
            'job_id'               => $job->id,
            'cv_id'                => $cv->id,
            'status'               => 'pending',
        ]);

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $appId,
            'from_status'    => null,
            'to_status'      => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $companyUser->id,
            'event_type'   => 'application.created',
            'related_type' => 'application',
            'related_id'   => $appId,
        ]);

        // TC-APP-02: Danh sách và chi tiết đơn ứng tuyển của ứng viên
        $this->actingAs($candidateUser, 'sanctum')
            ->getJson('/api/v1/candidate/applications')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $detailRes = $this->actingAs($candidateUser, 'sanctum')
            ->getJson("/api/v1/candidate/applications/{$appId}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.application.id', $appId)
            ->assertJsonMissing(['storage_key']);
    }

    public function test_apply_blocks_unconfirmed_cv_non_public_job_and_duplicate(): void
    {
        [$companyUser, $company, $publicJob] = $this->createVerifiedCompanyWithJob();
        [$candidateUser, $profile, $draftCv] = $this->createCandidateWithCV('draft');

        // TC-APP-03a: Chặn CV chưa confirmed
        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$publicJob->id}/applications", ['cv_id' => $draftCv->id])
            ->assertStatus(409);

        // Xác nhận CV để test các điều kiện Job & Duplicate
        $draftCv->extractions()->update(['review_status' => 'confirmed', 'confirmed_at' => now()]);

        // TC-APP-03b: Chặn tin hết hạn hoặc chưa duyệt
        [, , $expiredJob] = $this->createVerifiedCompanyWithJob(['expires_at' => now()->subDay()]);
        [, , $pendingJob] = $this->createVerifiedCompanyWithJob(['review_status' => 'pending', 'publication_status' => 'not_public']);

        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$expiredJob->id}/applications", ['cv_id' => $draftCv->id])
            ->assertStatus(409);

        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$pendingJob->id}/applications", ['cv_id' => $draftCv->id])
            ->assertStatus(409);

        // Nộp lần 1 thành công
        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$publicJob->id}/applications", ['cv_id' => $draftCv->id])
            ->assertStatus(201);

        // TC-APP-03c: Chặn nộp trùng lần 2 vào cùng tin (D-01)
        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$publicJob->id}/applications", ['cv_id' => $draftCv->id])
            ->assertStatus(409);
    }

    public function test_candidate_withdraw_cancels_scheduled_interview_and_blocks_terminal_states(): void
    {
        [$companyUser, $company, $job] = $this->createVerifiedCompanyWithJob();
        [$candidateUser, $profile, $cv] = $this->createCandidateWithCV('confirmed');

        $app = new Application();
        $app->candidate_profile_id = $profile->id;
        $app->job_id               = $job->id;
        $app->cv_id                = $cv->id;
        $app->status               = 'interview';
        $app->applied_at           = now()->subHour();
        $app->save();

        $interview = Interview::create([
            'application_id'     => $app->id,
            'scheduled_at'       => now()->addDays(2),
            'location_or_mode'   => 'Online Google Meet',
            'status'             => 'scheduled',
            'created_by_user_id' => $companyUser->id,
        ]);

        // TC-APP-04: Rút đơn khi đang interview -> thành công & hủy lịch scheduled
        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/candidate/applications/{$app->id}/withdraw")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'withdrawn');

        $this->assertDatabaseHas('interviews', [
            'id'     => $interview->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'    => $companyUser->id,
            'event_type' => 'application.withdrawn',
            'related_id' => $app->id,
        ]);

        // TC-APP-05: Rút lại đơn đã withdrawn -> bị chặn 409
        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/candidate/applications/{$app->id}/withdraw")
            ->assertStatus(409);

        // Nộp lại vào tin đã từng rút vẫn bị chặn 409 theo D-01
        $this->actingAs($candidateUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$job->id}/applications", ['cv_id' => $cv->id])
            ->assertStatus(409);
    }

    public function test_company_state_transitions_and_terminal_locks(): void
    {
        [$companyUser, $company, $job] = $this->createVerifiedCompanyWithJob();
        [$candidateUser, $profile, $cv] = $this->createCandidateWithCV('confirmed');

        $app = new Application();
        $app->candidate_profile_id = $profile->id;
        $app->job_id               = $job->id;
        $app->cv_id                = $cv->id;
        $app->status               = 'pending';
        $app->applied_at           = now();
        $app->save();

        // TC-APP-06: Doanh nghiệp xem danh sách & chi tiết hồ sơ (ẩn storage_key)
        $this->actingAs($companyUser, 'sanctum')
            ->getJson("/api/v1/company/jobs/{$job->id}/applications")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->actingAs($companyUser, 'sanctum')
            ->getJson("/api/v1/company/applications/{$app->id}")
            ->assertStatus(200)
            ->assertJsonMissing(['storage_key']);

        // TC-APP-07: Chuyển trạng thái pending -> reviewing -> interview
        $this->actingAs($companyUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$app->id}/status", [
                'new_status' => 'reviewing',
                'note'       => 'Hồ sơ phù hợp, chuyển vòng đánh giá.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'reviewing');

        // Ứng viên xem chi tiết không thấy note nội bộ của doanh nghiệp
        $this->actingAs($candidateUser, 'sanctum')
            ->getJson("/api/v1/candidate/applications/{$app->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.application.history.0.note', null);

        $this->actingAs($companyUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$app->id}/status", [
                'new_status' => 'interview',
            ])
            ->assertStatus(200);

        // TC-APP-08: Đang có lịch scheduled -> cấm chuyển ngược interview -> reviewing
        Interview::create([
            'application_id'     => $app->id,
            'scheduled_at'       => now()->addDay(),
            'location_or_mode'   => 'Office',
            'status'             => 'scheduled',
            'created_by_user_id' => $companyUser->id,
        ]);

        $this->actingAs($companyUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$app->id}/status", [
                'new_status' => 'reviewing',
            ])
            ->assertStatus(409);

        // TC-APP-09: Chuyển sang trạng thái kết thúc passed -> cấm mọi chuyển tiếp sau đó
        $this->actingAs($companyUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$app->id}/status", [
                'new_status' => 'passed',
            ])
            ->assertStatus(200);

        $this->actingAs($companyUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$app->id}/status", [
                'new_status' => 'rejected',
            ])
            ->assertStatus(409);
    }
}
