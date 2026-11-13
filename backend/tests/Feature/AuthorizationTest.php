<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateCV;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedScenario(): array
    {
        Storage::fake('private_cvs');

        $jg = JobGroup::firstOrCreate(['name' => 'IT'], ['is_active' => true]);
        $et = EmploymentType::firstOrCreate(['name' => 'FT'], ['is_active' => true]);
        $wm = WorkMode::firstOrCreate(['name' => 'On'], ['is_active' => true]);
        $sl = SeniorityLevel::firstOrCreate(['name' => 'Jr'], ['is_active' => true]);

        // Company A (Verified - Chủ sở hữu Job)
        $companyAUser = new User();
        $companyAUser->name = 'Comp A';
        $companyAUser->email = 'compa@example.com';
        $companyAUser->password = bcrypt('Password123!');
        $companyAUser->role = 'company';
        $companyAUser->account_status = 'active';
        $companyAUser->save();

        $companyA = new Company();
        $companyA->user_id = $companyAUser->id;
        $companyA->company_name = 'Company A';
        $companyA->verification_status = 'verified';
        $companyA->save();

        // Company B (Verified - Công ty khác)
        $companyBUser = new User();
        $companyBUser->name = 'Comp B';
        $companyBUser->email = 'compb@example.com';
        $companyBUser->password = bcrypt('Password123!');
        $companyBUser->role = 'company';
        $companyBUser->account_status = 'active';
        $companyBUser->save();

        $companyB = new Company();
        $companyB->user_id = $companyBUser->id;
        $companyB->company_name = 'Company B';
        $companyB->verification_status = 'verified';
        $companyB->save();

        // Company C (Unverified)
        $companyCUser = new User();
        $companyCUser->name = 'Comp C';
        $companyCUser->email = 'compc@example.com';
        $companyCUser->password = bcrypt('Password123!');
        $companyCUser->role = 'company';
        $companyCUser->account_status = 'active';
        $companyCUser->save();

        $companyC = new Company();
        $companyC->user_id = $companyCUser->id;
        $companyC->company_name = 'Company C Unverified';
        $companyC->verification_status = 'unverified';
        $companyC->save();

        $jobA = new Job();
        $jobA->company_id = $companyA->id;
        $jobA->job_group_id = $jg->id;
        $jobA->employment_type_id = $et->id;
        $jobA->work_mode_id = $wm->id;
        $jobA->seniority_level_id = $sl->id;
        $jobA->title = 'Senior PHP Engineer';
        $jobA->description = 'Desc';
        $jobA->requirements_text = 'Req';
        $jobA->review_status = 'approved';
        $jobA->publication_status = 'active';
        $jobA->expires_at = now()->addDays(10);
        $jobA->save();

        // Candidate A & CV #1 (Snapshot lúc nộp đơn)
        $candAUser = new User();
        $candAUser->name = 'Cand A';
        $candAUser->email = 'canda@example.com';
        $candAUser->password = bcrypt('Password123!');
        $candAUser->role = 'candidate';
        $candAUser->account_status = 'active';
        $candAUser->save();

        $candAProfile = CandidateProfile::create(['user_id' => $candAUser->id]);

        $cv1Key = 'private/2026/11/cv-snapshot-v1.pdf';
        Storage::disk('private_cvs')->put($cv1Key, '%PDF-1.4 SNAPSHOT_V1_CONTENT');

        $cv1 = new CandidateCV();
        $cv1->candidate_profile_id = $candAProfile->id;
        $cv1->original_filename    = 'cv_v1.pdf';
        $cv1->storage_key          = $cv1Key;
        $cv1->file_size            = 2048;
        $cv1->mime_type            = 'application/pdf';
        $cv1->sha256               = hash('sha256', 'cv1');
        $cv1->parse_status         = 'processed';
        $cv1->uploaded_at          = now()->subHour();
        $cv1->save();

        // Candidate B (Ứng viên khác)
        $candBUser = new User();
        $candBUser->name = 'Cand B';
        $candBUser->email = 'candb@example.com';
        $candBUser->password = bcrypt('Password123!');
        $candBUser->role = 'candidate';
        $candBUser->account_status = 'active';
        $candBUser->save();

        CandidateProfile::create(['user_id' => $candBUser->id]);

        $application = new Application();
        $application->candidate_profile_id = $candAProfile->id;
        $application->job_id               = $jobA->id;
        $application->cv_id                = $cv1->id;
        $application->status               = 'pending';
        $application->applied_at           = now()->subMinutes(30);
        $application->save();

        return compact(
            'companyAUser',
            'companyBUser',
            'companyCUser',
            'jobA',
            'candAUser',
            'candAProfile',
            'candBUser',
            'cv1',
            'application'
        );
    }

    public function test_verified_owner_streams_exact_cv_snapshot_even_after_new_cv_upload(): void
    {
        $ctx = $this->seedScenario();

        // Ứng viên A upload CV #2 mới hơn sau khi đã nộp đơn (Quyết định D-02)
        $cv2Key = 'private/2026/11/cv-newer-v2.pdf';
        Storage::disk('private_cvs')->put($cv2Key, '%PDF-1.4 NEWER_V2_CONTENT');

        $cv2 = new CandidateCV();
        $cv2->candidate_profile_id = $ctx['candAProfile']->id;
        $cv2->original_filename    = 'cv_v2.pdf';
        $cv2->storage_key          = $cv2Key;
        $cv2->file_size            = 4096;
        $cv2->mime_type            = 'application/pdf';
        $cv2->sha256               = hash('sha256', 'cv2');
        $cv2->parse_status         = 'processed';
        $cv2->uploaded_at          = now();
        $cv2->save();

        // TC-SEC-CV-01 & TC-SEC-CV-02: Company A chỉ nhận được đúng CV #1 đã nộp
        $response = $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->get("/api/v1/company/applications/{$ctx['application']->id}/cv");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('SNAPSHOT_V1_CONTENT', $streamedContent);
        $this->assertStringNotContainsString('NEWER_V2_CONTENT', $streamedContent);
    }

    public function test_cross_company_and_unverified_company_blocked_from_cv_and_applications(): void
    {
        $ctx = $this->seedScenario();
        $appId = $ctx['application']->id;
        $jobId = $ctx['jobA']->id;

        // TC-SEC-CV-03: Company B (verified nhưng không sở hữu Job A) bị chặn toàn bộ API-APP-05..08
        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->getJson("/api/v1/company/jobs/{$jobId}/applications")
            ->assertStatus(403);

        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->getJson("/api/v1/company/applications/{$appId}")
            ->assertStatus(403);

        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->get("/api/v1/company/applications/{$appId}/cv")
            ->assertStatus(403);

        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->patchJson("/api/v1/company/applications/{$appId}/status", ['new_status' => 'reviewing'])
            ->assertStatus(403);

        // TC-SEC-CV-04: Company C (unverified) bị chặn tải CV
        $this->actingAs($ctx['companyCUser'], 'sanctum')
            ->get("/api/v1/company/applications/{$appId}/cv")
            ->assertStatus(403);
    }

    public function test_cross_candidate_idor_blocked(): void
    {
        $ctx = $this->seedScenario();
        $appId = $ctx['application']->id;

        // TC-SEC-CV-05: Candidate B xem hoặc rút đơn của Candidate A -> 403
        $this->actingAs($ctx['candBUser'], 'sanctum')
            ->getJson("/api/v1/candidate/applications/{$appId}")
            ->assertStatus(403);

        $this->actingAs($ctx['candBUser'], 'sanctum')
            ->postJson("/api/v1/candidate/applications/{$appId}/withdraw")
            ->assertStatus(403);

        // Candidate gọi endpoint tải CV của Company -> 403
        $this->actingAs($ctx['candAUser'], 'sanctum')
            ->getJson("/api/v1/company/applications/{$appId}/cv")
            ->assertStatus(403);
    }

    public function test_unauthenticated_access_to_cv_stream_blocked(): void
    {
        $ctx = $this->seedScenario();
        $appId = $ctx['application']->id;

        // TC-SEC-CV-06: Chưa đăng nhập -> 401
        $this->getJson("/api/v1/company/applications/{$appId}/cv")
            ->assertStatus(401);
    }
}
