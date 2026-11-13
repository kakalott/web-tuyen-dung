<?php

namespace Tests\Unit;

use App\Clients\PythonAIClient;
use App\Models\AIMatchResult;
use App\Models\CVExtraction;
use App\Models\Job;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Services\MatchingService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class MatchingFormulaTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_null_coverage_and_fingerprint_determinism(): void
    {
        $mockClient = Mockery::mock(PythonAIClient::class);
        $service = new MatchingService($mockClient);

        // Thứ tự mảng skill IDs khác nhau nhưng cùng tập hợp -> phải sinh ra cùng 1 input_fingerprint
        $fp1 = $service->buildFingerprints(
            "Experienced PHP Laravel Developer",
            [5, 3, 4],
            "Backend Engineer\nBuild APIs",
            [],
            [10, 8],
            'hybrid-v1'
        );

        $fp2 = $service->buildFingerprints(
            "  experienced   php laravel developer  ",
            [3, 4, 5],
            "backend engineer\nbuild apis",
            [],
            [8, 10],
            'hybrid-v1'
        );

        $this->assertSame($fp1['cv_text_hash'], $fp2['cv_text_hash']);
        $this->assertSame($fp1['job_text_hash'], $fp2['job_text_hash']);
        $this->assertSame($fp1['input_fingerprint'], $fp2['input_fingerprint']);
        $this->assertSame([], $fp1['sorted_req_skills']);
    }

    public function test_stale_hash_detects_cv_jd_and_version_changes(): void
    {
        $mockClient = Mockery::mock(PythonAIClient::class);
        $service = new MatchingService($mockClient);

        $skillPhp = new Skill(['name' => 'PHP']);
        $skillPhp->id = 3;
        $skillPhp->setRelation('pivot', (object) ['review_status' => 'confirmed']);

        $extraction = new CVExtraction(['confirmed_text' => 'PHP Laravel Developer']);
        $extraction->setRelation('skills', new Collection([$skillPhp]));

        $jobSkill = new JobSkill(['skill_id' => 3, 'requirement_type' => 'required']);
        $job = new Job([
            'title'             => 'Backend Developer',
            'description'       => 'PHP APIs',
            'requirements_text' => 'MySQL',
        ]);
        $job->setRelation('jobSkills', new Collection([$jobSkill]));

        $initialFp = $service->buildFingerprints(
            'PHP Laravel Developer',
            [3],
            "Backend Developer\nPHP APIs\nMySQL",
            [3],
            [],
            'hybrid-v1'
        );

        $result = new AIMatchResult([
            'input_fingerprint' => $initialFp['input_fingerprint'],
            'algorithm_version' => 'hybrid-v1',
        ]);

        // Chưa thay đổi gì -> không bị stale
        $this->assertFalse($service->isResultStale($result, $extraction, $job));

        // 1. Ứng viên sửa confirmed_text -> bị đánh dấu stale
        $extraction->confirmed_text = 'PHP Laravel and Docker Developer';
        $this->assertTrue($service->isResultStale($result, $extraction, $job));
        $extraction->confirmed_text = 'PHP Laravel Developer';

        // 2. Doanh nghiệp đổi yêu cầu JD -> bị đánh dấu stale
        $job->description = 'PHP APIs and Microservices';
        $this->assertTrue($service->isResultStale($result, $extraction, $job));
        $job->description = 'PHP APIs';

        // 3. Nâng cấp algorithm_version -> bị đánh dấu stale
        $result->algorithm_version = 'hybrid-v2';
        $this->assertTrue($service->isResultStale($result, $extraction, $job));
    }
}
