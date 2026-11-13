<?php

namespace Tests\Feature;

use App\Clients\PythonAIClient;
use App\Models\AIMatchResult;
use App\Models\Application;
use App\Models\CandidateCV;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CVExtraction;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobGroup;
use App\Models\JobSkill;
use App\Models\SeniorityLevel;
use App\Models\Skill;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedBaseFixtures(): array
    {
        $jg = JobGroup::firstOrCreate(['name' => 'IT'], ['is_active' => true]);
        $et = EmploymentType::firstOrCreate(['name' => 'Full-time'], ['is_active' => true]);
        $wm = WorkMode::firstOrCreate(['name' => 'Onsite'], ['is_active' => true]);
        $sl = SeniorityLevel::firstOrCreate(['name' => 'Junior'], ['is_active' => true]);

        $skillPhp = Skill::create(['name' => 'PHP', 'normalized_name' => 'php', 'is_active' => true]);
        $skillLaravel = Skill::create(['name' => 'Laravel', 'normalized_name' => 'laravel', 'is_active' => true]);
        $skillDocker = Skill::create(['name' => 'Docker', 'normalized_name' => 'docker', 'is_active' => true]);

        $companyUser = new User();
        $companyUser->name = 'HR Rep';
        $companyUser->email = 'hr_' . uniqid() . '@example.com';
        $companyUser->password = bcrypt('Password123!');
        $companyUser->role = 'company';
        $companyUser->account_status = 'active';
        $companyUser->save();

        $company = new Company();
        $company->user_id = $companyUser->id;
        $company->company_name = 'AI Tech Vietnam';
        $company->verification_status = 'verified';
        $company->save();

        // Job 1: Có required skills [PHP, Laravel, Docker]
        $jobWithSkills = new Job();
        $jobWithSkills->company_id         = $company->id;
        $jobWithSkills->job_group_id       = $jg->id;
        $jobWithSkills->employment_type_id = $et->id;
        $jobWithSkills->work_mode_id       = $wm->id;
        $jobWithSkills->seniority_level_id = $sl->id;
        $jobWithSkills->title              = 'Senior PHP Laravel Engineer';
        $jobWithSkills->description        = 'Develop backend services.';
        $jobWithSkills->requirements_text  = 'PHP, Laravel, Docker.';
        $jobWithSkills->review_status      = 'approved';
        $jobWithSkills->publication_status = 'active';
        $jobWithSkills->expires_at         = now()->addDays(15);
        $jobWithSkills->save();

        JobSkill::create(['job_id' => $jobWithSkills->id, 'skill_id' => $skillPhp->id, 'requirement_type' => 'required']);
        JobSkill::create(['job_id' => $jobWithSkills->id, 'skill_id' => $skillLaravel->id, 'requirement_type' => 'required']);
        JobSkill::create(['job_id' => $jobWithSkills->id, 'skill_id' => $skillDocker->id, 'requirement_type' => 'required']);

        // Job 2: Không có required skills (skill_coverage phải là null)
        $jobNoSkills = new Job();
        $jobNoSkills->company_id         = $company->id;
        $jobNoSkills->job_group_id       = $jg->id;
        $jobNoSkills->employment_type_id = $et->id;
        $jobNoSkills->work_mode_id       = $wm->id;
        $jobNoSkills->seniority_level_id = $sl->id;
        $jobNoSkills->title              = 'General Software Intern';
        $jobNoSkills->description        = 'Join engineering team.';
        $jobNoSkills->requirements_text  = 'Good attitude.';
        $jobNoSkills->review_status      = 'approved';
        $jobNoSkills->publication_status = 'active';
        $jobNoSkills->expires_at         = now()->addDays(15);
        $jobNoSkills->save();

        // Job 3: Pending (không được phép xuất hiện trong recommendations)
        $jobPending = new Job();
        $jobPending->company_id         = $company->id;
        $jobPending->job_group_id       = $jg->id;
        $jobPending->employment_type_id = $et->id;
        $jobPending->work_mode_id       = $wm->id;
        $jobPending->seniority_level_id = $sl->id;
        $jobPending->title              = 'Unapproved Secret Job';
        $jobPending->description        = 'Pending review.';
        $jobPending->requirements_text  = 'PHP.';
        $jobPending->review_status      = 'pending';
        $jobPending->publication_status = 'not_public';
        $jobPending->expires_at         = now()->addDays(15);
        $jobPending->save();

        // Candidate với CV đã confirmed [PHP, Laravel]
        $candUser = new User();
        $candUser->name = 'Nguyen Van A';
        $candUser->email = 'cand_' . uniqid() . '@example.com';
        $candUser->password = bcrypt('Password123!');
        $candUser->role = 'candidate';
        $candUser->account_status = 'active';
        $candUser->save();

        $profile = CandidateProfile::create(['user_id' => $candUser->id]);

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'cv_a.pdf';
        $cv->storage_key          = 'private/2026/11/cv-a.pdf';
        $cv->file_size            = 10240;
        $cv->mime_type            = 'application/pdf';
        $cv->sha256               = hash('sha256', 'dummy-a');
        $cv->parse_status         = 'processed';
        $cv->uploaded_at          = now();
        $cv->save();

        $extraction = CVExtraction::create([
            'cv_id'          => $cv->id,
            'extracted_text' => 'PHP Laravel Developer',
            'confirmed_text' => 'PHP Laravel Developer with 3 years experience',
            'review_status'  => 'confirmed',
            'confirmed_at'   => now(),
        ]);
        $extraction->skills()->attach([
            $skillPhp->id     => ['review_status' => 'confirmed', 'source' => 'detected'],
            $skillLaravel->id => ['review_status' => 'confirmed', 'source' => 'detected'],
        ]);

        return compact(
            'companyUser',
            'company',
            'jobWithSkills',
            'jobNoSkills',
            'jobPending',
            'candUser',
            'profile',
            'cv',
            'extraction',
            'skillPhp',
            'skillLaravel',
            'skillDocker'
        );
    }

    public function test_recommendations_happy_path_null_coverage_and_cache_reuse(): void
    {
        $ctx = $this->seedBaseFixtures();

        $mockClient = Mockery::mock(PythonAIClient::class);
        // Chỉ gọi Python đúng 2 lần (1 lần cho jobNoSkills, 1 lần cho jobWithSkills) trong lần gọi đầu tiên
        $mockClient->shouldReceive('scoreMatching')
            ->twice()
            ->andReturnUsing(function (array $payload) use ($ctx) {
                if (!empty($payload['jd_required_skill_ids'])) {
                    return [
                        'baseline_score'           => 65.50,
                        'semantic_similarity'      => 0.80,
                        'skill_coverage'           => 0.666667,
                        'match_score'              => 74.67,
                        'matched_skills'           => [$ctx['skillPhp']->id, $ctx['skillLaravel']->id],
                        'missing_skills'           => [$ctx['skillDocker']->id],
                        'matched_preferred_skills' => [],
                        'model_name'               => 'paraphrase-multilingual-MiniLM-L12-v2',
                        'algorithm_version'        => 'hybrid-v1',
                        'warnings'                 => [],
                    ];
                }

                return [
                    'baseline_score'           => 40.00,
                    'semantic_similarity'      => 0.85,
                    'skill_coverage'           => null,
                    'match_score'              => 85.00,
                    'matched_skills'           => [],
                    'missing_skills'           => [],
                    'matched_preferred_skills' => [],
                    'model_name'               => 'paraphrase-multilingual-MiniLM-L12-v2',
                    'algorithm_version'        => 'hybrid-v1',
                    'warnings'                 => ['NO_REQUIRED_SKILLS'],
                ];
            });

        $this->app->instance(PythonAIClient::class, $mockClient);

        // Lần gọi 1: Cache Miss -> Tính điểm, lưu DB và sắp xếp giảm dần (85.00 đứng trước 74.67)
        $res1 = $this->actingAs($ctx['candUser'], 'sanctum')
            ->getJson("/api/v1/candidate/recommendations?cv_id={$ctx['cv']->id}");

        $res1->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.job_id', $ctx['jobNoSkills']->id)
            ->assertJsonPath('data.items.0.match_score', 85)
            ->assertJsonPath('data.items.0.skill_coverage', null)
            ->assertJsonPath('data.items.1.job_id', $ctx['jobWithSkills']->id)
            ->assertJsonPath('data.items.1.match_score', 74.67)
            ->assertJsonPath('data.items.1.skill_coverage', 0.666667)
            ->assertJsonCount(2, 'data.items.1.matched_skills')
            ->assertJsonCount(1, 'data.items.1.missing_skills');

        $this->assertDatabaseCount('ai_match_results', 2);
        $this->assertDatabaseCount('ai_match_result_skills', 3);

        // Lần gọi 2 với cùng CV & JD: Cache Hit -> Không gọi lại PythonAIClient (Mockery verify twice())
        $res2 = $this->actingAs($ctx['candUser'], 'sanctum')
            ->getJson("/api/v1/candidate/recommendations?cv_id={$ctx['cv']->id}");

        $res2->assertStatus(200)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_recommendations_blocks_unconfirmed_cv_and_cross_owner_cv(): void
    {
        $ctx = $this->seedBaseFixtures();

        // Chuyển extraction sang draft -> bị chặn 409 CV_NOT_CONFIRMED
        $ctx['extraction']->update(['review_status' => 'draft', 'confirmed_at' => null]);

        $this->actingAs($ctx['candUser'], 'sanctum')
            ->getJson("/api/v1/candidate/recommendations?cv_id={$ctx['cv']->id}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CV_NOT_CONFIRMED');

        // Ứng viên khác dùng cv_id của Cand A -> bị chặn 422
        $otherCand = new User();
        $otherCand->name = 'Other Cand';
        $otherCand->email = 'other_' . uniqid() . '@example.com';
        $otherCand->password = bcrypt('Password123!');
        $otherCand->role = 'candidate';
        $otherCand->account_status = 'active';
        $otherCand->save();

        CandidateProfile::create(['user_id' => $otherCand->id]);

        $this->actingAs($otherCand, 'sanctum')
            ->getJson("/api/v1/candidate/recommendations?cv_id={$ctx['cv']->id}")
            ->assertStatus(422);
    }

    public function test_company_applicant_ranking_sort_match_desc_without_excluding_low_scores(): void
    {
        $ctx = $this->seedBaseFixtures();

        // Tạo thêm Candidate B có điểm thấp hơn
        $candBUser = new User();
        $candBUser->name = 'Tran Thi B (Low Score)';
        $candBUser->email = 'cand_b_' . uniqid() . '@example.com';
        $candBUser->password = bcrypt('Password123!');
        $candBUser->role = 'candidate';
        $candBUser->account_status = 'active';
        $candBUser->save();

        $profileB = CandidateProfile::create(['user_id' => $candBUser->id]);

        $cvB = new CandidateCV();
        $cvB->candidate_profile_id = $profileB->id;
        $cvB->original_filename    = 'cv_b.pdf';
        $cvB->storage_key          = 'private/2026/11/cv-b.pdf';
        $cvB->file_size            = 10240;
        $cvB->mime_type            = 'application/pdf';
        $cvB->sha256               = hash('sha256', 'dummy-b');
        $cvB->parse_status         = 'processed';
        $cvB->uploaded_at          = now();
        $cvB->save();

        CVExtraction::create([
            'cv_id'          => $cvB->id,
            'extracted_text' => 'Marketing intern',
            'confirmed_text' => 'Marketing intern without coding skills',
            'review_status'  => 'confirmed',
            'confirmed_at'   => now(),
        ]);

        // Candidate B nộp sớm hơn, Candidate A nộp muộn hơn
        $appB = new Application();
        $appB->candidate_profile_id = $profileB->id;
        $appB->job_id               = $ctx['jobWithSkills']->id;
        $appB->cv_id                = $cvB->id;
        $appB->status               = 'pending';
        $appB->applied_at           = now()->subHours(2);
        $appB->save();

        $appA = new Application();
        $appA->candidate_profile_id = $ctx['profile']->id;
        $appA->job_id               = $ctx['jobWithSkills']->id;
        $appA->cv_id                = $ctx['cv']->id;
        $appA->status               = 'pending';
        $appA->applied_at           = now()->subHour();
        $appA->save();

        $mockClient = Mockery::mock(PythonAIClient::class);
        $mockClient->shouldReceive('scoreMatching')
            ->twice()
            ->andReturnUsing(function (array $payload) {
                $isCandA = str_contains($payload['cv_text'], 'PHP Laravel');
                return [
                    'baseline_score'           => $isCandA ? 70.0 : 12.0,
                    'semantic_similarity'      => $isCandA ? 0.85 : 0.25,
                    'skill_coverage'           => $isCandA ? 0.666667 : 0.0,
                    'match_score'              => $isCandA ? 77.67 : 15.0,
                    'matched_skills'           => [],
                    'missing_skills'           => [],
                    'matched_preferred_skills' => [],
                    'model_name'               => 'paraphrase-multilingual-MiniLM-L12-v2',
                    'algorithm_version'        => 'hybrid-v1',
                    'warnings'                 => [],
                ];
            });
        $this->app->instance(PythonAIClient::class, $mockClient);

        // Doanh nghiệp gọi API-APP-05?sort=match_desc -> Cand A (77.67) đứng trước Cand B (15.0), không loại bỏ Cand B
        $res = $this->actingAs($ctx['companyUser'], 'sanctum')
            ->getJson("/api/v1/company/jobs/{$ctx['jobWithSkills']->id}/applications?sort=match_desc");

        $res->assertStatus(200)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $appA->id)
            ->assertJsonPath('data.items.0.match_score', 77.67)
            ->assertJsonPath('data.items.1.id', $appB->id)
            ->assertJsonPath('data.items.1.match_score', 15);
    }

    public function test_python_service_down_returns_502_without_persisting_fake_zero_score(): void
    {
        $ctx = $this->seedBaseFixtures();

        $mockClient = Mockery::mock(PythonAIClient::class);
        $mockClient->shouldReceive('scoreMatching')
            ->once()
            ->andThrow(new RuntimeException('AI_SERVICE_ERROR', 502));
        $this->app->instance(PythonAIClient::class, $mockClient);

        $this->actingAs($ctx['candUser'], 'sanctum')
            ->getJson("/api/v1/candidate/recommendations?cv_id={$ctx['cv']->id}")
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'AI_SERVICE_ERROR');

        // Đảm bảo không ghi bản ghi điểm 0 giả vào DB
        $this->assertDatabaseCount('ai_match_results', 0);
    }
}
