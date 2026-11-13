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

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    private function seedInterviewScenario(string $applicationStatus = 'reviewing'): array
    {
        $jg = JobGroup::firstOrCreate(['name' => 'IT Software'], ['is_active' => true]);
        $et = EmploymentType::firstOrCreate(['name' => 'Full-time'], ['is_active' => true]);
        $wm = WorkMode::firstOrCreate(['name' => 'Hybrid'], ['is_active' => true]);
        $sl = SeniorityLevel::firstOrCreate(['name' => 'Middle'], ['is_active' => true]);

        // Company A (Verified owner)
        $companyAUser = new User();
        $companyAUser->name = 'HR Alpha';
        $companyAUser->email = 'comp_a_' . uniqid() . '@example.com';
        $companyAUser->password = bcrypt('Password123!');
        $companyAUser->role = 'company';
        $companyAUser->account_status = 'active';
        $companyAUser->save();

        $companyA = new Company();
        $companyA->user_id             = $companyAUser->id;
        $companyA->company_name        = 'Alpha Tech Corp';
        $companyA->verification_status = 'verified';
        $companyA->save();

        // Company B (Verified other company - IDOR test)
        $companyBUser = new User();
        $companyBUser->name = 'HR Beta';
        $companyBUser->email = 'comp_b_' . uniqid() . '@example.com';
        $companyBUser->password = bcrypt('Password123!');
        $companyBUser->role = 'company';
        $companyBUser->account_status = 'active';
        $companyBUser->save();

        $companyB = new Company();
        $companyB->user_id             = $companyBUser->id;
        $companyB->company_name        = 'Beta Solutions';
        $companyB->verification_status = 'verified';
        $companyB->save();

        // Company C (Unverified company)
        $companyCUser = new User();
        $companyCUser->name = 'HR Gamma';
        $companyCUser->email = 'comp_c_' . uniqid() . '@example.com';
        $companyCUser->password = bcrypt('Password123!');
        $companyCUser->role = 'company';
        $companyCUser->account_status = 'active';
        $companyCUser->save();

        $companyC = new Company();
        $companyC->user_id             = $companyCUser->id;
        $companyC->company_name        = 'Gamma Startup';
        $companyC->verification_status = 'unverified';
        $companyC->save();

        $job = new Job();
        $job->company_id         = $companyA->id;
        $job->job_group_id       = $jg->id;
        $job->employment_type_id = $et->id;
        $job->work_mode_id       = $wm->id;
        $job->seniority_level_id = $sl->id;
        $job->title              = 'Backend Laravel Engineer';
        $job->description        = 'Develop REST APIs.';
        $job->requirements_text  = 'Laravel, MySQL.';
        $job->review_status      = 'approved';
        $job->publication_status = 'active';
        $job->expires_at         = now()->addDays(15);
        $job->save();

        $candidateUser = new User();
        $candidateUser->name = 'Candidate John';
        $candidateUser->email = 'cand_' . uniqid() . '@example.com';
        $candidateUser->password = bcrypt('Password123!');
        $candidateUser->role = 'candidate';
        $candidateUser->account_status = 'active';
        $candidateUser->save();

        $profile = CandidateProfile::create(['user_id' => $candidateUser->id]);

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'candidate_cv.pdf';
        $cv->storage_key          = 'private/2026/11/interview-cv-' . uniqid() . '.pdf';
        $cv->file_size            = 102400;
        $cv->mime_type            = 'application/pdf';
        $cv->sha256               = hash('sha256', 'dummy-interview-cv-' . uniqid());
        $cv->parse_status         = 'processed';
        $cv->uploaded_at          = now()->subDay();
        $cv->save();

        CVExtraction::create([
            'cv_id'          => $cv->id,
            'extracted_text' => 'Laravel Developer',
            'confirmed_text' => 'Laravel Developer with 4 years experience',
            'review_status'  => 'confirmed',
            'confirmed_at'   => now()->subDay(),
        ]);

        $application = new Application();
        $application->candidate_profile_id = $profile->id;
        $application->job_id               = $job->id;
        $application->cv_id                = $cv->id;
        $application->status               = $applicationStatus;
        $application->applied_at           = now()->subHours(5);
        $application->save();

        return compact(
            'companyAUser',
            'companyBUser',
            'companyCUser',
            'job',
            'candidateUser',
            'application'
        );
    }

    /**
     * TC-INT-01: Schedule moves reviewing -> interview, records history & notification, blocks duplicate scheduled.
     */
    public function test_schedule_interview_moves_reviewing_to_interview_and_blocks_duplicate(): void
    {
        $ctx = $this->seedInterviewScenario('reviewing');
        $appId = $ctx['application']->id;
        $scheduledAt = now()->addDays(3)->setSecond(0)->toIso8601String();

        $res = $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => $scheduledAt,
                'location_or_mode' => 'Google Meet: https://meet.google.com/abc-defg-hij',
                'notes'            => 'Vui lòng tham gia trước 5 phút.',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.interview.status', 'scheduled')
            ->assertJsonPath('data.application_status', 'interview');

        $interviewId = $res->json('data.interview.id');

        $this->assertDatabaseHas('applications', [
            'id'     => $appId,
            'status' => 'interview',
        ]);

        $this->assertDatabaseHas('application_status_histories', [
            'application_id'     => $appId,
            'from_status'        => 'reviewing',
            'to_status'          => 'interview',
            'changed_by_user_id' => $ctx['companyAUser']->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'interview.created',
            'related_type' => 'interview',
            'related_id'   => $interviewId,
        ]);

        // D-03: Không cho phép tạo lịch scheduled thứ 2 khi đang có lịch scheduled active -> 409
        $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(4)->toIso8601String(),
                'location_or_mode' => 'Văn phòng tầng 5',
            ])
            ->assertStatus(409);
    }

    /**
     * TC-INT-02: Update scheduled interview updates fields and sends interview.updated notification.
     */
    public function test_update_scheduled_interview_happy_path_and_invalid_state_block(): void
    {
        $ctx = $this->seedInterviewScenario('interview');
        $interview = new Interview();
        $interview->application_id     = $ctx['application']->id;
        $interview->scheduled_at       = now()->addDays(2);
        $interview->location_or_mode   = 'Online Zoom';
        $interview->notes              = 'Old note';
        $interview->status             = 'scheduled';
        $interview->created_by_user_id = $ctx['companyAUser']->id;
        $interview->save();

        $newTime = now()->addDays(5)->setSecond(0)->toIso8601String();

        $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->patchJson("/api/v1/company/interviews/{$interview->id}", [
                'scheduled_at'     => $newTime,
                'location_or_mode' => 'Tầng 12 Tòa nhà Tech, Hà Nội',
                'notes'            => 'Mang theo laptop cá nhân.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.interview.location_or_mode', 'Tầng 12 Tòa nhà Tech, Hà Nội')
            ->assertJsonPath('data.interview.notes', 'Mang theo laptop cá nhân.');

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'interview.updated',
            'related_type' => 'interview',
            'related_id'   => $interview->id,
        ]);

        // Đánh dấu completed rồi thử update lại -> 409 STATE_CONFLICT
        $interview->status = 'completed';
        $interview->save();

        $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->patchJson("/api/v1/company/interviews/{$interview->id}", [
                'scheduled_at'     => now()->addDays(6)->toIso8601String(),
                'location_or_mode' => 'New place',
            ])
            ->assertStatus(409);
    }

    /**
     * TC-INT-03: Cancelling last scheduled interview reverts application from interview to reviewing.
     */
    public function test_cancel_last_scheduled_interview_reverts_application_to_reviewing(): void
    {
        $ctx = $this->seedInterviewScenario('interview');
        $appId = $ctx['application']->id;

        $interview = new Interview();
        $interview->application_id     = $appId;
        $interview->scheduled_at       = now()->addDays(2);
        $interview->location_or_mode   = 'Google Meet';
        $interview->status             = 'scheduled';
        $interview->created_by_user_id = $ctx['companyAUser']->id;
        $interview->save();

        $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interview->id}/cancel", [
                'reason' => 'Hội đồng tuyển dụng bận đột xuất.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.interview.status', 'cancelled')
            ->assertJsonPath('data.interview.cancel_reason', 'Hội đồng tuyển dụng bận đột xuất.')
            ->assertJsonPath('data.application_status', 'reviewing');

        $this->assertDatabaseHas('applications', [
            'id'     => $appId,
            'status' => 'reviewing',
        ]);

        $this->assertDatabaseHas('application_status_histories', [
            'application_id' => $appId,
            'from_status'    => 'interview',
            'to_status'      => 'reviewing',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'interview.cancelled',
            'related_type' => 'interview',
            'related_id'   => $interview->id,
        ]);
    }

    /**
     * TC-INT-04 & TC-INT-05: Completed and no_show keep application.status unchanged (no auto pass/reject).
     */
    public function test_completed_and_no_show_keep_application_status_independent(): void
    {
        // 1. TC-INT-04: Mark completed -> application remains 'interview'
        $ctx1 = $this->seedInterviewScenario('interview');
        $interview1 = new Interview();
        $interview1->application_id     = $ctx1['application']->id;
        $interview1->scheduled_at       = now()->addHour();
        $interview1->location_or_mode   = 'Office Room 302';
        $interview1->status             = 'scheduled';
        $interview1->created_by_user_id = $ctx1['companyAUser']->id;
        $interview1->save();

        $this->actingAs($ctx1['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interview1->id}/complete", [
                'note' => 'Ứng viên trả lời tốt phần kiến trúc hệ thống.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.interview.status', 'completed')
            ->assertJsonPath('data.application_status', 'interview');

        $this->assertDatabaseHas('applications', [
            'id'     => $ctx1['application']->id,
            'status' => 'interview',
        ]);

        // 2. TC-INT-05: Mark no_show -> application remains 'interview' (NOT auto-rejected)
        $ctx2 = $this->seedInterviewScenario('interview');
        $interview2 = new Interview();
        $interview2->application_id     = $ctx2['application']->id;
        $interview2->scheduled_at       = now()->addHour();
        $interview2->location_or_mode   = 'Online Meet';
        $interview2->status             = 'scheduled';
        $interview2->created_by_user_id = $ctx2['companyAUser']->id;
        $interview2->save();

        $this->actingAs($ctx2['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interview2->id}/no-show", [
                'note' => 'Quá 20 phút ứng viên không tham gia.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.interview.status', 'no_show')
            ->assertJsonPath('data.application_status', 'interview');

        $this->assertDatabaseHas('applications', [
            'id'     => $ctx2['application']->id,
            'status' => 'interview',
        ]);
    }

    /**
     * Security: Cross-company IDOR, unverified company, and terminal application guards.
     */
    public function test_authorization_and_terminal_application_guards(): void
    {
        $ctx = $this->seedInterviewScenario('reviewing');
        $appId = $ctx['application']->id;

        // Company B (other verified company) cannot schedule -> 403
        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(2)->toIso8601String(),
                'location_or_mode' => 'Online',
            ])
            ->assertStatus(403);

        // Company C (unverified) cannot schedule -> 403
        $this->actingAs($ctx['companyCUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(2)->toIso8601String(),
                'location_or_mode' => 'Online',
            ])
            ->assertStatus(403);

        // Company A schedules validly
        $interviewId = $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(2)->toIso8601String(),
                'location_or_mode' => 'Online',
            ])
            ->json('data.interview.id');

        // Company B cannot update/cancel/complete/no-show Company A's interview -> 403
        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interviewId}/cancel")
            ->assertStatus(403);

        $this->actingAs($ctx['companyBUser'], 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interviewId}/complete")
            ->assertStatus(403);

        // Terminal application (passed/rejected/withdrawn) blocks scheduling -> 409
        $ctx['application']->status = 'rejected';
        $ctx['application']->save();

        $this->actingAs($ctx['companyAUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(3)->toIso8601String(),
                'location_or_mode' => 'Online',
            ])
            ->assertStatus(409);
    }
}
