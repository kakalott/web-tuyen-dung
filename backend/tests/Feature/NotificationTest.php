<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CandidateCV;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CVExtraction;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobGroup;
use App\Models\Notification;
use App\Models\SeniorityLevel;
use App\Models\User;
use App\Models\WorkMode;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function seedActorsAndCatalog(): array
    {
        $admin = new User();
        $admin->name = 'Admin User';
        $admin->email = 'admin_' . uniqid() . '@example.com';
        $admin->password = bcrypt('Password123!');
        $admin->role = 'admin';
        $admin->account_status = 'active';
        $admin->save();

        $companyUser = new User();
        $companyUser->name = 'HR TechCorp';
        $companyUser->email = 'comp_' . uniqid() . '@example.com';
        $companyUser->password = bcrypt('Password123!');
        $companyUser->role = 'company';
        $companyUser->account_status = 'active';
        $companyUser->save();

        $company = new Company();
        $company->user_id             = $companyUser->id;
        $company->company_name        = 'TechCorp Vietnam';
        $company->verification_status = 'verified';
        $company->save();

        $otherUser = new User();
        $otherUser->name = 'Other Candidate';
        $otherUser->email = 'other_' . uniqid() . '@example.com';
        $otherUser->password = bcrypt('Password123!');
        $otherUser->role = 'candidate';
        $otherUser->account_status = 'active';
        $otherUser->save();
        CandidateProfile::create(['user_id' => $otherUser->id]);

        $candidateUser = new User();
        $candidateUser->name = 'Candidate Main';
        $candidateUser->email = 'cand_' . uniqid() . '@example.com';
        $candidateUser->password = bcrypt('Password123!');
        $candidateUser->role = 'candidate';
        $candidateUser->account_status = 'active';
        $candidateUser->save();
        $profile = CandidateProfile::create(['user_id' => $candidateUser->id]);

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'cv_noti.pdf';
        $cv->storage_key          = 'private/2026/11/cv-noti-' . uniqid() . '.pdf';
        $cv->file_size            = 102400;
        $cv->mime_type            = 'application/pdf';
        $cv->sha256               = hash('sha256', 'dummy-noti-cv-' . uniqid());
        $cv->parse_status         = 'processed';
        $cv->uploaded_at          = now()->subDay();
        $cv->save();

        CVExtraction::create([
            'cv_id'          => $cv->id,
            'extracted_text' => 'Senior Laravel & Vue Engineer',
            'confirmed_text' => 'Senior Laravel & Vue Engineer with 5 years experience',
            'review_status'  => 'confirmed',
            'confirmed_at'   => now()->subDay(),
        ]);

        $jg = JobGroup::firstOrCreate(['name' => 'IT Software'], ['is_active' => true]);
        $et = EmploymentType::firstOrCreate(['name' => 'Full-time'], ['is_active' => true]);
        $wm = WorkMode::firstOrCreate(['name' => 'Hybrid'], ['is_active' => true]);
        $sl = SeniorityLevel::firstOrCreate(['name' => 'Senior'], ['is_active' => true]);

        return compact(
            'admin',
            'companyUser',
            'company',
            'candidateUser',
            'otherUser',
            'profile',
            'cv',
            'jg',
            'et',
            'wm',
            'sl'
        );
    }

    /**
     * TC-NOTI-01: All 9 Business Events Generate Notifications with exact event_type, recipient, and XSS stripping.
     */
    public function test_all_nine_business_events_generate_expected_notifications(): void
    {
        $ctx = $this->seedActorsAndCatalog();

        $jobPayload = [
            'title'              => 'Lead Backend Engineer',
            'description'        => 'Architect APIs.',
            'requirements_text'  => 'PHP, Laravel.',
            'job_group_id'       => $ctx['jg']->id,
            'employment_type_id' => $ctx['et']->id,
            'work_mode_id'       => $ctx['wm']->id,
            'seniority_level_id' => $ctx['sl']->id,
            'expires_at'         => now()->addDays(20)->toIso8601String(),
        ];

        // Tạo 3 tin để kiểm thử approve, reject, hide
        $job1Id = $this->actingAs($ctx['companyUser'], 'sanctum')
            ->postJson('/api/v1/company/jobs', $jobPayload)
            ->json('data.id');

        $job2Id = $this->actingAs($ctx['companyUser'], 'sanctum')
            ->postJson('/api/v1/company/jobs', array_merge($jobPayload, ['title' => 'Rejected Role']))
            ->json('data.id');

        $job3Id = $this->actingAs($ctx['companyUser'], 'sanctum')
            ->postJson('/api/v1/company/jobs', array_merge($jobPayload, ['title' => 'Hidden Role']))
            ->json('data.id');

        // 1. job.approved -> Company.user_id
        $this->actingAs($ctx['admin'], 'sanctum')
            ->patchJson("/api/v1/admin/jobs/{$job1Id}/review", ['decision' => 'approve'])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['companyUser']->id,
            'event_type'   => 'job.approved',
            'related_type' => 'job',
            'related_id'   => $job1Id,
        ]);

        // 2. job.rejected -> Company.user_id (kèm reason)
        $this->actingAs($ctx['admin'], 'sanctum')
            ->patchJson("/api/v1/admin/jobs/{$job2Id}/review", [
                'decision' => 'reject',
                'reason'   => 'Thiếu mô tả quyền lợi',
            ])
            ->assertStatus(200);

        $rejectedNoti = Notification::where('event_type', 'job.rejected')
            ->where('related_id', $job2Id)
            ->first();
        $this->assertNotNull($rejectedNoti);
        $this->assertEquals($ctx['companyUser']->id, $rejectedNoti->user_id);
        $this->assertStringContainsString('Thiếu mô tả quyền lợi', $rejectedNoti->content);

        // 3. job.hidden -> Company.user_id (kèm reason)
        // Duyệt job3 trước để job3 chuyển sang 'active'
        $this->actingAs($ctx['admin'], 'sanctum')
            ->patchJson("/api/v1/admin/jobs/{$job3Id}/review", ['decision' => 'approve'])
            ->assertStatus(200);

        $this->actingAs($ctx['admin'], 'sanctum')
            ->patchJson("/api/v1/admin/jobs/{$job3Id}/hide", [
                'reason' => 'Vi phạm điều khoản đăng tin',
            ])
            ->assertStatus(200);

        $hiddenNoti = Notification::where('event_type', 'job.hidden')
            ->where('related_id', $job3Id)
            ->first();
        $this->assertNotNull($hiddenNoti);
        $this->assertEquals($ctx['companyUser']->id, $hiddenNoti->user_id);
        $this->assertStringContainsString('Vi phạm điều khoản đăng tin', $hiddenNoti->content);

        // 4. application.created -> Company.user_id
        $applyRes = $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->postJson("/api/v1/jobs/{$job1Id}/applications", ['cv_id' => $ctx['cv']->id])
            ->assertStatus(201);
        $appId = $applyRes->json('data.application_id');

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['companyUser']->id,
            'event_type'   => 'application.created',
            'related_type' => 'application',
            'related_id'   => $appId,
        ]);

        // 5. application.status_changed -> Candidate.user_id
        $this->actingAs($ctx['companyUser'], 'sanctum')
            ->patchJson("/api/v1/company/applications/{$appId}/status", [
                'new_status' => 'reviewing',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'application.status_changed',
            'related_type' => 'application',
            'related_id'   => $appId,
        ]);

        // 6. interview.created -> Candidate.user_id
        $intRes = $this->actingAs($ctx['companyUser'], 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(3)->toIso8601String(),
                'location_or_mode' => 'Google Meet',
            ])
            ->assertStatus(201);
        $interviewId = $intRes->json('data.interview.id');

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'interview.created',
            'related_type' => 'interview',
            'related_id'   => $interviewId,
        ]);

        // 7. interview.updated -> Candidate.user_id
        $this->actingAs($ctx['companyUser'], 'sanctum')
            ->patchJson("/api/v1/company/interviews/{$interviewId}", [
                'scheduled_at'     => now()->addDays(4)->toIso8601String(),
                'location_or_mode' => 'Zoom Room 2',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'interview.updated',
            'related_type' => 'interview',
            'related_id'   => $interviewId,
        ]);

        // 8. interview.cancelled -> Candidate.user_id
        $this->actingAs($ctx['companyUser'], 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interviewId}/cancel", [
                'reason' => 'Đổi lịch sang tuần sau',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['candidateUser']->id,
            'event_type'   => 'interview.cancelled',
            'related_type' => 'interview',
            'related_id'   => $interviewId,
        ]);

        // 9. application.withdrawn -> Company.user_id
        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->postJson("/api/v1/candidate/applications/{$appId}/withdraw")
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id'      => $ctx['companyUser']->id,
            'event_type'   => 'application.withdrawn',
            'related_type' => 'application',
            'related_id'   => $appId,
        ]);

        // Kiểm tra chống Stored XSS trên NotificationService::notifyUser
        $xssNoti = app(NotificationService::class)->notifyUser($ctx['candidateUser']->id, 'test.xss', [
            'content'      => '<script>alert("xss")</script><b>Thông báo sạch</b>',
            'related_type' => 'job',
            'related_id'   => $job1Id,
        ]);
        $this->assertEquals('alert("xss")Thông báo sạch', $xssNoti->content);
    }

    /**
     * TC-NOTI-02: Filter unread/read, mark single read idempotently, and mark all read scoped to auth user.
     */
    public function test_filter_unread_mark_single_read_idempotent_and_mark_all_read(): void
    {
        $ctx = $this->seedActorsAndCatalog();
        $service = app(NotificationService::class);

        $n1 = $service->notifyUser($ctx['candidateUser']->id, 'application.status_changed', [
            'content'      => 'Thông báo 1',
            'related_type' => 'application',
            'related_id'   => 10,
            'created_at'   => now()->subMinutes(10),
        ]);
        $n2 = $service->notifyUser($ctx['candidateUser']->id, 'interview.created', [
            'content'      => 'Thông báo 2',
            'related_type' => 'interview',
            'related_id'   => 20,
            'created_at'   => now()->subMinutes(5),
        ]);
        $n3 = $service->notifyUser($ctx['candidateUser']->id, 'interview.updated', [
            'content'      => 'Thông báo 3',
            'related_type' => 'interview',
            'related_id'   => 20,
            'created_at'   => now(),
        ]);

        // Thông báo của user khác không được phép bị ảnh hưởng bởi read-all
        $otherNoti = $service->notifyUser($ctx['otherUser']->id, 'job.approved', [
            'content'      => 'Thông báo của user khác',
            'related_type' => 'job',
            'related_id'   => 99,
        ]);

        // 1. Lấy danh sách unread -> 3 items, unread_count = 3
        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->getJson('/api/v1/notifications?read_filter=unread')
            ->assertStatus(200)
            ->assertJsonPath('data.unread_count', 3)
            ->assertJsonCount(3, 'data.items');

        // 2. Đánh dấu n1 đã đọc lần 1
        $res1 = $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->patchJson("/api/v1/notifications/{$n1->id}/read")
            ->assertStatus(200);

        $firstReadAt = $res1->json('data.read_at');
        $this->assertNotNull($firstReadAt);

        // Chờ 1 giây và gọi lại lần 2 -> idempotent, giữ nguyên read_at cũ
        $this->travel(2)->seconds();
        $res2 = $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->patchJson("/api/v1/notifications/{$n1->id}/read")
            ->assertStatus(200);

        $this->assertEquals($firstReadAt, $res2->json('data.read_at'));

        // Lọc read_filter=read -> chỉ có n1; unread -> còn n2, n3 (unread_count = 2)
        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->getJson('/api/v1/notifications?read_filter=read')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $n1->id)
            ->assertJsonPath('data.unread_count', 2);

        // 3. Mark all read -> count_updated = 2, unread_count = 0
        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('data.count_updated', 2);

        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->getJson('/api/v1/notifications?read_filter=unread')
            ->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonCount(0, 'data.items');

        // Thông báo của otherUser vẫn nguyên trạng chưa đọc
        $this->assertNull($otherNoti->fresh()->read_at);
    }

    /**
     * TC-NOTI-03: Cross-user notification access blocked (IDOR protection) & unauthenticated blocked.
     */
    public function test_cross_user_notification_access_blocked(): void
    {
        $ctx = $this->seedActorsAndCatalog();
        $service = app(NotificationService::class);

        $otherNoti = $service->notifyUser($ctx['otherUser']->id, 'application.status_changed', [
            'content'      => 'Thông báo riêng tư của User B',
            'related_type' => 'application',
            'related_id'   => 1,
        ]);

        // Unauthenticated -> 401
        $this->getJson('/api/v1/notifications')->assertStatus(401);
        $this->patchJson("/api/v1/notifications/{$otherNoti->id}/read")->assertStatus(401);

        // Candidate A cố tình đọc thông báo của User B -> 403 FORBIDDEN
        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->patchJson("/api/v1/notifications/{$otherNoti->id}/read")
            ->assertStatus(403);

        $this->assertNull($otherNoti->fresh()->read_at);

        // Client cố tình truyền user_id lậu qua query string -> bị bỏ qua hoàn toàn
        $this->actingAs($ctx['candidateUser'], 'sanctum')
            ->getJson("/api/v1/notifications?user_id={$ctx['otherUser']->id}")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }

    /**
     * TC-NOTI-04: Transaction atomicity — failed business transition creates zero orphan notifications.
     */
    public function test_failed_business_transition_creates_no_orphan_notification(): void
    {
        $ctx = $this->seedActorsAndCatalog();

        $job = new Job();
        $job->company_id         = $ctx['company']->id;
        $job->job_group_id       = $ctx['jg']->id;
        $job->employment_type_id = $ctx['et']->id;
        $job->work_mode_id       = $ctx['wm']->id;
        $job->seniority_level_id = $ctx['sl']->id;
        $job->title              = 'Atomicity Check Job';
        $job->description        = 'Desc';
        $job->requirements_text  = 'Req';
        $job->review_status      = 'approved';
        $job->publication_status = 'active';
        $job->expires_at         = now()->addDays(10);
        $job->save();

        $application = new Application();
        $application->candidate_profile_id = $ctx['profile']->id;
        $application->job_id               = $job->id;
        $application->cv_id                = $ctx['cv']->id;
        $application->status               = 'passed'; // Trạng thái kết thúc (terminal)
        $application->applied_at           = now()->subDay();
        $application->save();

        $initialCount = Notification::count();

        // Thao tác chuyển trạng thái bất hợp lệ từ terminal state -> 409 STATE_CONFLICT
        $this->actingAs($ctx['companyUser'], 'sanctum')
            ->patchJson("/api/v1/company/applications/{$application->id}/status", [
                'new_status' => 'rejected',
            ])
            ->assertStatus(409);

        // Đảm bảo không phát sinh bất kỳ bản ghi notification rác nào
        $this->assertEquals($initialCount, Notification::count());
    }
}
