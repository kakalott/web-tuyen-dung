<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MatchingTest extends TestCase
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

    private function createVerifiedCompanyWithJob(string $title = 'Backend Laravel Developer', array $requiredSkills = [], array $preferredSkills = []): array
    {
        $companyUser = new User();
        $companyUser->name = 'Acme HR';
        $companyUser->email = 'hr_' . uniqid() . '@acme.com';
        $companyUser->password = bcrypt('Password123!');
        $companyUser->role = 'company';
        $companyUser->account_status = 'active';
        $companyUser->save();

        $company = new Company();
        $company->user_id             = $companyUser->id;
        $company->company_name        = 'Acme Tech';
        $company->verification_status = 'verified';
        $company->save();

        $lookups = $this->createLookupIds();
        $job = new Job();
        $job->company_id         = $company->id;
        $job->title              = $title;
        $job->description        = 'Develop APIs with Laravel and Python';
        $job->requirements_text  = 'Solid knowledge in PHP and Python';
        $job->review_status      = 'approved';
        $job->publication_status = 'active';
        $job->expires_at         = now()->addDays(30);
        foreach ($lookups as $k => $v) {
            $job->{$k} = $v;
        }
        $job->save();

        foreach ($requiredSkills as $skillId) {
            JobSkill::create([
                'job_id'           => $job->id,
                'skill_id'         => $skillId,
                'requirement_type' => 'required',
            ]);
        }

        foreach ($preferredSkills as $skillId) {
            JobSkill::create([
                'job_id'           => $job->id,
                'skill_id'         => $skillId,
                'requirement_type' => 'preferred',
            ]);
        }

        return [$companyUser, $company, $job];
    }

    private function createCandidateWithCV(string $reviewStatus = 'confirmed', array $confirmedSkillIds = []): array
    {
        $candidateUser = new User();
        $candidateUser->name = 'John Candidate';
        $candidateUser->email = 'candidate_' . uniqid() . '@example.com';
        $candidateUser->password = bcrypt('Password123!');
        $candidateUser->role = 'candidate';
        $candidateUser->account_status = 'active';
        $candidateUser->save();

        $profile = CandidateProfile::create(['user_id' => $candidateUser->id]);

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'john_cv.pdf';
        $cv->storage_key          = 'private/cvs/' . uniqid() . '.pdf';
        $cv->file_size            = 1024;
        $cv->mime_type            = 'application/pdf';
        $cv->sha256               = hash('sha256', 'dummy_content');
        $cv->parse_status         = 'processed';
        $cv->uploaded_at          = now();
        $cv->save();

        $extraction = CVExtraction::create([
            'cv_id'          => $cv->id,
            'extracted_text' => 'Experienced Laravel and Python Developer',
            'confirmed_text' => $reviewStatus === 'confirmed' ? 'Experienced Laravel and Python Developer' : null,
            'review_status'  => $reviewStatus,
            'confirmed_at'   => $reviewStatus === 'confirmed' ? now() : null,
        ]);

        if ($reviewStatus === 'confirmed' && !empty($confirmedSkillIds)) {
            foreach ($confirmedSkillIds as $skillId) {
                $extraction->skills()->attach($skillId, [
                    'source'        => 'detected',
                    'review_status' => 'confirmed',
                ]);
            }
        }

        return [$candidateUser, $profile, $cv, $extraction];
    }

    /**
     * TC-MATCH-01: Candidate gets job recommendations based on confirmed CV (API-AI-01).
     */
    public function test_candidate_recommendations_happy_path(): void
    {
        $skillPhp = Skill::create(['name' => 'PHP', 'normalized_name' => 'php', 'is_active' => true]);
        $skillPython = Skill::create(['name' => 'Python', 'normalized_name' => 'python', 'is_active' => true]);

        [$companyUser, $company, $job1] = $this->createVerifiedCompanyWithJob('Laravel Backend', [$skillPhp->id]);
        [$companyUser2, $company2, $job2] = $this->createVerifiedCompanyWithJob('Python AI Engineer', [$skillPython->id]);

        [$candUser, $candProfile, $cv, $ext] = $this->createCandidateWithCV('confirmed', [$skillPhp->id]);

        // Mock Python AI Service
        Http::fake([
            '*/internal/v1/matching/score' => Http::response([
                'data' => [
                    'baseline_score'      => 65.0,
                    'semantic_similarity' => 0.85,
                    'skill_coverage'      => 1.0,
                    'match_score'         => 91.0,
                    'algorithm_version'   => 'hybrid-v1',
                    'model_name'          => 'mock-model',
                    'warnings'            => [],
                ],
                'request_id' => 'req_mock',
            ], 200),
        ]);

        $res = $this->actingAs($candUser, 'sanctum')->getJson("/api/v1/candidate/recommendations?cv_id={$cv->id}");

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'job' => ['id', 'title', 'company_name'],
                            'match_result' => ['id', 'match_score', 'baseline_score', 'semantic_similarity', 'skill_coverage', 'matched_skills'],
                            'score_stale',
                        ],
                    ],
                    'coverage_note',
                    'pagination' => ['page', 'page_size', 'total', 'total_pages'],
                ],
                'request_id',
            ]);

        $this->assertNotEmpty($res->json('data.items'));
    }

    /**
     * TC-MATCH-02: Candidate with unconfirmed CV is rejected (409 CV_NOT_CONFIRMED).
     */
    public function test_recommendations_blocks_unconfirmed_cv(): void
    {
        [$candUser, $candProfile, $cv, $ext] = $this->createCandidateWithCV('draft');

        $res = $this->actingAs($candUser, 'sanctum')->getJson("/api/v1/candidate/recommendations?cv_id={$cv->id}");

        $res->assertStatus(409)
            ->assertJsonPath('error.code', 'CV_NOT_CONFIRMED');
    }

    /**
     * TC-MATCH-03: IDOR protection on candidate recommendation (cannot use another candidate's CV).
     */
    public function test_recommendations_idor_protection(): void
    {
        [$candUser1, $profile1, $cv1] = $this->createCandidateWithCV('confirmed');
        [$candUser2, $profile2, $cv2] = $this->createCandidateWithCV('confirmed');

        $res = $this->actingAs($candUser1, 'sanctum')->getJson("/api/v1/candidate/recommendations?cv_id={$cv2->id}");

        $res->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * TC-MATCH-04: Cache hit via input_fingerprint prevents duplicate AI calls (D-11).
     */
    public function test_cache_hit_via_input_fingerprint(): void
    {
        $skillPhp = Skill::create(['name' => 'PHP', 'normalized_name' => 'php', 'is_active' => true]);
        [$companyUser, $company, $job] = $this->createVerifiedCompanyWithJob('PHP Backend', [$skillPhp->id]);
        [$candUser, $candProfile, $cv] = $this->createCandidateWithCV('confirmed', [$skillPhp->id]);

        $callCount = 0;
        Http::fake([
            '*/internal/v1/matching/score' => function ($request) use (&$callCount) {
                $callCount++;
                return Http::response([
                    'data' => [
                        'baseline_score'      => 70.0,
                        'semantic_similarity' => 0.80,
                        'skill_coverage'      => 1.0,
                        'match_score'         => 88.0,
                        'algorithm_version'   => 'hybrid-v1',
                        'model_name'          => 'mock-model',
                        'warnings'            => [],
                    ],
                    'request_id' => 'req_mock',
                ], 200);
            },
        ]);

        // Lần 1: Cache Miss -> Gọi AI
        $res1 = $this->actingAs($candUser, 'sanctum')->getJson("/api/v1/candidate/recommendations?cv_id={$cv->id}");
        $res1->assertStatus(200);
        $this->assertEquals(1, $callCount);

        // Lần 2: Cache Hit -> Không gọi thêm AI
        $res2 = $this->actingAs($candUser, 'sanctum')->getJson("/api/v1/candidate/recommendations?cv_id={$cv->id}");
        $res2->assertStatus(200);
        $this->assertEquals(1, $callCount, 'AI Service must not be called again on cache hit.');
    }

    /**
     * TC-MATCH-05: Company sorts applications by sort=match_desc (FR-APP-06, BR-AI-07) without dropping low scores.
     */
    public function test_company_applications_sorted_by_match_desc(): void
    {
        $skillPhp = Skill::create(['name' => 'PHP', 'normalized_name' => 'php', 'is_active' => true]);
        [$companyUser, $company, $job] = $this->createVerifiedCompanyWithJob('Senior PHP', [$skillPhp->id]);

        [$cand1, $p1, $cv1] = $this->createCandidateWithCV('confirmed', [$skillPhp->id]);
        [$cand2, $p2, $cv2] = $this->createCandidateWithCV('confirmed', []);

        // Candidate 1 nộp đơn
        $app1 = new Application();
        $app1->candidate_profile_id = $p1->id;
        $app1->job_id               = $job->id;
        $app1->cv_id                = $cv1->id;
        $app1->status               = 'pending';
        $app1->applied_at           = now()->subMinutes(10);
        $app1->save();

        // Candidate 2 nộp đơn
        $app2 = new Application();
        $app2->candidate_profile_id = $p2->id;
        $app2->job_id               = $job->id;
        $app2->cv_id                = $cv2->id;
        $app2->status               = 'pending';
        $app2->applied_at           = now()->subMinutes(5);
        $app2->save();

        // Giả lập điểm số: cv1 có điểm 90.0, cv2 có điểm thấp 30.0
        Http::fake([
            '*/internal/v1/matching/score' => function ($request) {
                $payload = $request->data();
                $isCand1 = count($payload['cv_confirmed_skill_ids'] ?? []) > 0;
                return Http::response([
                    'data' => [
                        'baseline_score'      => $isCand1 ? 80.0 : 20.0,
                        'semantic_similarity' => $isCand1 ? 0.90 : 0.40,
                        'skill_coverage'      => $isCand1 ? 1.0 : 0.0,
                        'match_score'         => $isCand1 ? 94.0 : 24.0,
                        'algorithm_version'   => 'hybrid-v1',
                        'model_name'          => 'mock-model',
                        'warnings'            => [],
                    ],
                    'request_id' => 'req_mock',
                ], 200);
            },
        ]);

        $res = $this->actingAs($companyUser, 'sanctum')->getJson("/api/v1/company/jobs/{$job->id}/applications?sort=match_desc");

        $res->assertStatus(200);
        $data = $res->json('data.items');

        $this->assertCount(2, $data, 'BR-AI-07: Low score candidate must NOT be excluded.');
        $this->assertEquals($app1->id, $data[0]['id'], 'Candidate with higher score must be ranked first.');
        $this->assertEquals($app2->id, $data[1]['id'], 'Candidate with lower score must still be present.');
    }
}
