<?php

namespace Tests\Feature;

use App\Clients\PythonAIClient;
use App\Models\CandidateCV;
use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class FullSystemE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedLookups(): array
    {
        $jg = JobGroup::create(['name' => 'Software Engineering', 'is_active' => true]);
        $et = EmploymentType::create(['name' => 'Full-time', 'is_active' => true]);
        $wm = WorkMode::create(['name' => 'Hybrid', 'is_active' => true]);
        $sl = SeniorityLevel::create(['name' => 'Middle', 'is_active' => true]);

        return compact('jg', 'et', 'wm', 'sl');
    }

    private function createAdmin(): User
    {
        $admin = new User();
        $admin->name = 'System Admin';
        $admin->email = 'admin_' . uniqid() . '@system.local';
        $admin->password = bcrypt('AdminPassword123!');
        $admin->role = 'admin';
        $admin->account_status = 'active';
        $admin->save();

        return $admin;
    }

    /**
     * FLOW 1: Auth & Onboarding (P-AUTH-01, P-AUTH-02)
     */
    public function test_flow_1_auth_and_onboarding(): void
    {
        // 1. Đăng ký tài khoản Candidate mới
        $candRes = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Nguyen Van Ứng Viên',
            'email'                 => 'candidate_' . uniqid() . '@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role'                  => 'candidate',
        ]);
        $candRes->assertStatus(201);
        $candUser = User::where('email', $candRes->json('data.email'))->first();
        $this->assertNotNull($candUser);
        $this->assertEquals('candidate', $candUser->role);
        $this->assertNotNull($candUser->candidateProfile);

        // 2. Đăng ký tài khoản Company mới
        $compRes = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Tran HR Recruiter',
            'email'                 => 'company_' . uniqid() . '@company.local',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role'                  => 'company',
            'company_name'          => 'Công Ty TNHH Công Nghệ Mới',
        ]);
        $compRes->assertStatus(201);
        $compUser = User::where('email', $compRes->json('data.email'))->first();
        $this->assertNotNull($compUser);
        $this->assertEquals('company', $compUser->role);
        $this->assertNotNull($compUser->company);
        $this->assertEquals('unverified', $compUser->company->verification_status);

        // 3. Candidate & Company không thể truy cập /admin/dashboard (403 Forbidden)
        $this->actingAs($candUser, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);

        $this->actingAs($compUser, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertStatus(403);

        // 4. Đăng xuất
        $this->actingAs($candUser, 'sanctum')
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);
    }

    /**
     * FLOW 2: Admin Catalog & Candidate CV Preparation (P-ADM-06, P-CAN-01..04)
     */
    public function test_flow_2_admin_catalog_and_candidate_cv_preparation(): array
    {
        $admin = $this->createAdmin();

        // 1. Admin tạo Skill "FastAPI" và Alias "fast-api"
        $skillRes = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/catalog/skills', [
                'name'      => 'FastAPI',
                'is_active' => true,
            ]);
        $skillRes->assertStatus(201);
        $skillId = $skillRes->json('data.id');

        $aliasRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/catalog/skills/{$skillId}/aliases", [
                'alias' => 'fast-api',
            ]);
        $aliasRes->assertStatus(201);

        // 2. Tạo Candidate
        $candUser = new User();
        $candUser->name = 'Le Candidate';
        $candUser->email = 'candidate_flow2_' . uniqid() . '@example.com';
        $candUser->password = bcrypt('Password123!');
        $candUser->role = 'candidate';
        $candUser->account_status = 'active';
        $candUser->save();
        $candUser->candidateProfile()->create([
            'phone'    => '0901234567',
            'location' => 'TP. Ho Chi Minh',
            'summary'  => 'Senior Python & Web Developer',
        ]);

        // Cập nhật Profile
        $this->actingAs($candUser, 'sanctum')
            ->patchJson('/api/v1/candidate/profile', [
                'phone'    => '0987654321',
                'location' => 'Ha Noi',
                'summary'  => 'Lead Backend Python & FastAPI Developer',
            ])->assertStatus(200);

        // Thêm Education & Experience
        Sanctum::actingAs($candUser);
        $this->postJson('/api/v1/candidate/educations', [
            'institution'    => 'Đại học Bách Khoa',
            'degree'         => 'Kỹ sư CNTT',
            'field_of_study' => 'Khoa học máy tính',
            'start_date'     => '2016-09-01',
            'end_date'       => '2021-06-30',
        ])->assertStatus(201);

        $this->postJson('/api/v1/candidate/experiences', [
            'employer_name' => 'FPT Software',
            'job_title'     => 'Senior Python Developer',
            'start_date'    => '2021-07-01',
            'is_current'    => true,
        ])->assertStatus(201);

        // Tìm kiếm skill qua alias và sync vào profile
        $searchRes = $this->actingAs($candUser, 'sanctum')
            ->getJson('/api/v1/catalog/skills?q=fast-api')
            ->assertStatus(200);
        $this->assertEquals('FastAPI', $searchRes->json('data.0.name'));

        $this->actingAs($candUser, 'sanctum')
            ->putJson('/api/v1/candidate/skills', [
                'skill_ids' => [$skillId],
            ])->assertStatus(200);

        // 3. Candidate Upload CV PDF & Mock AI Extraction
        Storage::fake('private_cvs');
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Title (Test CV) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $uploadedFile = UploadedFile::fake()->createWithContent('resume.pdf', $pdfContent);

        $mockAiClient = Mockery::mock(PythonAIClient::class);
        $mockAiClient->shouldReceive('extractCv')
            ->andReturn([
                'parser_status'   => 'success',
                'extracted_text'  => 'Nguyen Van A. Experience with FastAPI and Python microservices.',
                'normalized_text' => 'nguyen van a. experience with fastapi and python microservices.',
                'detected_skills' => [
                    ['skill_id' => $skillId, 'confidence' => 0.95],
                ],
                'warnings'        => [],
            ]);
        $this->app->instance(PythonAIClient::class, $mockAiClient);

        $uploadRes = $this->actingAs($candUser, 'sanctum')
            ->postJson('/api/v1/candidate/cvs', [
                'file' => $uploadedFile,
            ]);
        $uploadRes->assertStatus(201);
        $cvId = $uploadRes->json('data.id');

        // Xác nhận trích xuất (Confirm extraction)
        $confirmRes = $this->actingAs($candUser, 'sanctum')
            ->patchJson("/api/v1/candidate/cvs/{$cvId}/extraction", [
                'confirmed_text'     => 'Confirmed CV text with FastAPI skill.',
                'confirmed_skill_ids' => [$skillId],
            ]);
        $confirmRes->assertStatus(200);

        $cvModel = CandidateCV::find($cvId);
        $this->assertEquals('processed', $cvModel->parse_status);

        return compact('candUser', 'cvId', 'skillId');
    }

    /**
     * FLOW 3: Company Verification, Job Moderation & Public Search (P-COM-01..03, P-ADM-02..05, P-PUB-01..02)
     */
    public function test_flow_3_company_verification_job_moderation_public_search(): array
    {
        $admin = $this->createAdmin();
        $lookups = $this->seedLookups();

        $skillPhp = Skill::create(['name' => 'Python', 'normalized_name' => 'python', 'is_active' => true]);

        // 1. Tạo Company unverified
        $compUser = new User();
        $compUser->name = 'Recruiter HR';
        $compUser->email = 'hr_flow3_' . uniqid() . '@corp.local';
        $compUser->password = bcrypt('Password123!');
        $compUser->role = 'company';
        $compUser->account_status = 'active';
        $compUser->save();

        $company = Company::create([
            'user_id'             => $compUser->id,
            'company_name'        => 'AI Tech Corp',
            'description'         => 'Chuyên phát triển phần mềm AI',
            'website_url'         => 'https://aitech.local',
            'verification_status' => 'unverified',
        ]);

        // Unverified Company không thể tạo tin (BR-AUTH-02)
        $this->actingAs($compUser, 'sanctum')
            ->postJson('/api/v1/company/jobs', [
                'title'               => 'Backend Python Lead',
                'description'         => 'Mô tả công việc backend',
                'requirements_text'   => 'Yêu cầu 3 năm kinh nghiệm',
                'job_group_id'        => $lookups['jg']->id,
                'employment_type_id'  => $lookups['et']->id,
                'work_mode_id'        => $lookups['wm']->id,
                'seniority_level_id'  => $lookups['sl']->id,
                'expires_at'          => now()->addDays(14)->toIso8601String(),
                'required_skill_ids'  => [$skillPhp->id],
            ])->assertStatus(403);

        // 2. Admin duyệt xác minh công ty
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/companies/{$company->id}/verification", [
                'verification_status' => 'verified',
                'note'                => 'Đã đối chiếu thông tin doanh nghiệp hợp lệ.',
            ])->assertStatus(200);

        $this->assertEquals('verified', $company->fresh()->verification_status);

        // 3. Verified Company tạo tin thành công (trạng thái pending/not_public)
        $compUser = $compUser->fresh(['company']);
        Sanctum::actingAs($compUser);
        $jobRes = $this->postJson('/api/v1/company/jobs', [
            'title'               => 'Backend Python Lead',
            'description'         => 'Mô tả công việc backend',
            'requirements_text'   => 'Yêu cầu 3 năm kinh nghiệm',
            'job_group_id'        => $lookups['jg']->id,
            'employment_type_id'  => $lookups['et']->id,
            'work_mode_id'        => $lookups['wm']->id,
            'seniority_level_id'  => $lookups['sl']->id,
            'expires_at'          => now()->addDays(14)->toIso8601String(),
            'required_skill_ids'  => [$skillPhp->id],
        ]);
        $jobRes->assertStatus(201);
        $jobId = $jobRes->json('data.id');

        // Khách vãng lai xem danh sách tin công khai -> TIN CHƯA XUẤT HIỆN
        $publicSearch1 = $this->getJson('/api/v1/jobs');
        $items1 = $publicSearch1->json('data.items') ?? $publicSearch1->json('data') ?? [];
        $this->assertFalse(collect($items1)->pluck('id')->contains($jobId));

        // 4. Admin duyệt tin (Approve) -> approved + active
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/jobs/{$jobId}/review", [
                'decision' => 'approve',
            ])->assertStatus(200);

        // Khách vãng lai xem lại danh sách -> TIN ĐÃ XUẤT HIỆN
        $publicSearch2 = $this->getJson('/api/v1/jobs');
        $items2 = $publicSearch2->json('data.items') ?? $publicSearch2->json('data') ?? [];
        $this->assertTrue(collect($items2)->pluck('id')->contains($jobId));

        // Xem chi tiết tin công khai
        $this->getJson("/api/v1/jobs/{$jobId}")
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Backend Python Lead');

        return compact('compUser', 'company', 'jobId', 'skillPhp', 'lookups');
    }

    /**
     * FLOW 4: AI Matching Recommendations & Application Lifecycle (P-CAN-06, P-PUB-02, P-CAN-05, P-COM-04..06)
     */
    public function test_flow_4_ai_matching_recommendations_and_application_lifecycle(): array
    {
        $flow2 = $this->test_flow_2_admin_catalog_and_candidate_cv_preparation();
        $candUser = $flow2['candUser'];
        $cvId = $flow2['cvId'];
        $skillId = $flow2['skillId'];

        $flow3 = $this->test_flow_3_company_verification_job_moderation_public_search();
        $compUser = $flow3['compUser'];
        $jobId = $flow3['jobId'];

        // Mock AI Matching Client
        $mockAi = Mockery::mock(PythonAIClient::class);
        $mockAi->shouldReceive('scoreMatching')
            ->andReturn([
                'baseline_score'           => 80.0,
                'semantic_similarity'      => 0.85,
                'skill_coverage'           => 1.0,
                'match_score'              => 88.5,
                'matched_skills'           => [$skillId],
                'missing_skills'           => [],
                'matched_preferred_skills' => [],
                'model_name'               => 'paraphrase-multilingual-MiniLM-L12-v2',
                'algorithm_version'        => 'hybrid-v1',
                'warnings'                 => [],
            ]);
        $this->app->instance(PythonAIClient::class, $mockAi);

        // 1. Candidate xem Gợi ý việc làm (AI Recommendations)
        $recRes = $this->actingAs($candUser, 'sanctum')
            ->getJson("/api/v1/candidate/recommendations?cv_id={$cvId}");
        $recRes->assertStatus(200);

        // 2. Candidate Ứng tuyển vào Job
        $applyRes = $this->actingAs($candUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$jobId}/applications", [
                'cv_id' => $cvId,
            ]);
        $applyRes->assertStatus(201);
        $appId = $applyRes->json('data.application_id') ?? $applyRes->json('data.id');
        $this->assertEquals('pending', $applyRes->json('data.status'));

        // Thử ứng tuyển lại -> 409 STATE_CONFLICT (D-01)
        $this->actingAs($candUser, 'sanctum')
            ->postJson("/api/v1/jobs/{$jobId}/applications", [
                'cv_id' => $cvId,
            ])->assertStatus(409);

        // 3. Doanh nghiệp kiểm tra thông báo
        $notiRes = $this->actingAs($compUser, 'sanctum')
            ->getJson('/api/v1/notifications');
        $notiRes->assertStatus(200);
        $items = $notiRes->json('data.items') ?? $notiRes->json('data') ?? [];
        $this->assertTrue(collect($items)->pluck('event_type')->contains('application.created'));

        // 4. Doanh nghiệp xem danh sách ứng viên (sắp xếp match_desc)
        $compAppsRes = $this->actingAs($compUser, 'sanctum')
            ->getJson("/api/v1/company/jobs/{$jobId}/applications?sort=match_desc");
        $compAppsRes->assertStatus(200);
        $appsList = $compAppsRes->json('data.items') ?? $compAppsRes->json('data') ?? [];
        $this->assertEquals($appId, $appsList[0]['id']);

        // 5. Doanh nghiệp tải CV an toàn (Private stream)
        $this->actingAs($compUser, 'sanctum')
            ->get("/api/v1/company/applications/{$appId}/cv")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // 6. Doanh nghiệp chuyển trạng thái sang reviewing
        $this->actingAs($compUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$appId}/status", [
                'new_status' => 'reviewing',
            ])->assertStatus(200);

        return compact('candUser', 'compUser', 'appId', 'jobId', 'cvId');
    }

    /**
     * FLOW 5: Interview Scheduling, Transactional Notifications & Admin Dashboard (P-COM-06, P-CAN-05, P-CAN-07, P-ADM-01)
     */
    public function test_flow_5_interview_scheduling_notifications_and_admin_dashboard(): void
    {
        $flow4 = $this->test_flow_4_ai_matching_recommendations_and_application_lifecycle();
        $candUser = $flow4['candUser'];
        $compUser = $flow4['compUser'];
        $appId = $flow4['appId'];

        $admin = $this->createAdmin();

        // 1. Doanh nghiệp lên lịch phỏng vấn -> đơn tự động nhảy sang interview (BR-INT-02)
        $intRes = $this->actingAs($compUser, 'sanctum')
            ->postJson("/api/v1/company/applications/{$appId}/interviews", [
                'scheduled_at'     => now()->addDays(2)->format('Y-m-d H:i:s'),
                'location_or_mode' => 'Google Meet: https://meet.google.com/abc-defg-hij',
                'notes'            => 'Phỏng vấn kỹ thuật',
            ]);
        $intRes->assertStatus(201);
        $interviewId = $intRes->json('data.interview.id') ?? $intRes->json('data.id');

        $this->actingAs($compUser, 'sanctum')
            ->getJson("/api/v1/company/applications/{$appId}")
            ->assertStatus(200)
            ->assertJsonPath('data.application.status', 'interview');

        // 2. Đánh dấu hoàn thành phỏng vấn -> trạng thái đơn giữ nguyên interview (BR-INT-05)
        $this->actingAs($compUser, 'sanctum')
            ->postJson("/api/v1/company/interviews/{$interviewId}/complete")
            ->assertStatus(200);

        $this->actingAs($compUser, 'sanctum')
            ->getJson("/api/v1/company/applications/{$appId}")
            ->assertStatus(200)
            ->assertJsonPath('data.application.status', 'interview');

        // Doanh nghiệp chuyển đơn sang passed
        $this->actingAs($compUser, 'sanctum')
            ->patchJson("/api/v1/company/applications/{$appId}/status", [
                'new_status' => 'passed',
            ])->assertStatus(200);

        // 3. Candidate kiểm tra thông báo & Mark all as read
        $candNotiRes = $this->actingAs($candUser, 'sanctum')
            ->getJson('/api/v1/notifications?read_filter=unread');
        $candNotiRes->assertStatus(200);

        $this->actingAs($candUser, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertStatus(200);

        $unreadCountRes = $this->actingAs($candUser, 'sanctum')
            ->getJson('/api/v1/notifications?read_filter=unread');
        $this->assertEquals(0, $unreadCountRes->json('data.unread_count'));

        // 4. Admin kiểm tra Dashboard stats
        $dashRes = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/dashboard');
        $dashRes->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'users_total',
                    'companies_unverified',
                    'jobs_pending',
                    'applications_total',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $dashRes->json('data.users_total'));
        $this->assertGreaterThanOrEqual(1, $dashRes->json('data.applications_total'));
    }
}
