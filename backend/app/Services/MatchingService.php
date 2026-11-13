<?php

namespace App\Services;

use App\Clients\PythonAIClient;
use App\Models\AIMatchResult;
use App\Models\AIMatchResultSkill;
use App\Models\Application;
use App\Models\CandidateCV;
use App\Models\CVExtraction;
use App\Models\Job;
use App\Models\User;
use App\Support\DomainStates;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorInstance;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MatchingService
{
    public function __construct(private PythonAIClient $aiClient)
    {
    }

    /**
     * Tạo mã băm chuẩn hóa cho CV, JD và toàn bộ input + version (D-11).
     */
    public function buildFingerprints(
        string $confirmedText,
        array $confirmedSkillIds,
        string $jdText,
        array $requiredSkillIds,
        array $preferredSkillIds,
        string $algorithmVersion
    ): array {
        $normCv = $this->normalizeForHash($confirmedText);
        $normJd = $this->normalizeForHash($jdText);

        $sortedCvSkills = array_values(array_unique(array_map('intval', $confirmedSkillIds)));
        sort($sortedCvSkills);

        $sortedReqSkills = array_values(array_unique(array_map('intval', $requiredSkillIds)));
        sort($sortedReqSkills);

        $sortedPrefSkills = array_values(array_unique(array_map('intval', $preferredSkillIds)));
        sort($sortedPrefSkills);

        $cvTextHash = hash('sha256', $normCv . '|' . implode(',', $sortedCvSkills));
        $jobTextHash = hash(
            'sha256',
            $normJd . '|' . implode(',', $sortedReqSkills) . '|' . implode(',', $sortedPrefSkills)
        );
        $inputFingerprint = hash('sha256', $cvTextHash . '|' . $jobTextHash . '|' . $algorithmVersion);

        return [
            'cv_text_hash'        => $cvTextHash,
            'job_text_hash'       => $jobTextHash,
            'input_fingerprint'   => $inputFingerprint,
            'sorted_cv_skills'    => $sortedCvSkills,
            'sorted_req_skills'   => $sortedReqSkills,
            'sorted_pref_skills'  => $sortedPrefSkills,
        ];
    }

    /**
     * AI-001 & AI-002: Tính điểm đối sánh đơn lẻ có cache theo input_fingerprint.
     */
    public function calculate(int $cvId, int $jobId, ?int $appId = null): AIMatchResult
    {
        $cv = CandidateCV::whereNull('deleted_at')->findOrFail($cvId);

        $extraction = CVExtraction::with('skills')
            ->where('cv_id', $cv->id)
            ->where('review_status', 'confirmed')
            ->latest('confirmed_at')
            ->latest('id')
            ->first();

        if (!$extraction || trim((string) $extraction->confirmed_text) === '') {
            throw new RuntimeException('CV_NOT_CONFIRMED', 409);
        }

        $job = Job::with('jobSkills')->findOrFail($jobId);

        $confirmedSkillIds = $extraction->skills
            ->filter(fn ($skill) => ($skill->pivot->review_status ?? null) === 'confirmed')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $requiredSkillIds = $job->jobSkills
            ->where('requirement_type', 'required')
            ->pluck('skill_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $preferredSkillIds = $job->jobSkills
            ->where('requirement_type', 'preferred')
            ->pluck('skill_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $jdText = trim($job->title . "\n" . $job->description . "\n" . ($job->requirements_text ?? ''));
        $algorithmVersion = (string) config('services.ai_service.algorithm_version', 'hybrid-v1');

        $fp = $this->buildFingerprints(
            (string) $extraction->confirmed_text,
            $confirmedSkillIds,
            $jdText,
            $requiredSkillIds,
            $preferredSkillIds,
            $algorithmVersion
        );

        // 1. Kiểm tra Cache Hit theo (cv_id, job_id, input_fingerprint)
        $cached = AIMatchResult::with(['matchResultSkills.skill'])
            ->where('cv_id', $cv->id)
            ->where('job_id', $job->id)
            ->where('input_fingerprint', $fp['input_fingerprint'])
            ->latest('computed_at')
            ->latest('id')
            ->first();

        if ($cached) {
            // Nếu gọi từ luồng Application mà bản ghi cache chưa gắn application_id thì cập nhật tham chiếu nếu trống
            if ($appId !== null && $cached->application_id === null) {
                $cached->application_id = $appId;
                $cached->save();
            }
            return $cached;
        }

        // 2. Cache Miss -> Gọi Python AI Service BÊN NGOÀI DB transaction
        $aiResponse = $this->aiClient->scoreMatching([
            'cv_text'                 => (string) $extraction->confirmed_text,
            'jd_text'                 => $jdText,
            'cv_confirmed_skill_ids'  => $fp['sorted_cv_skills'],
            'jd_required_skill_ids'   => $fp['sorted_req_skills'],
            'jd_preferred_skill_ids'  => $fp['sorted_pref_skills'],
            'algorithm_version'       => $algorithmVersion,
        ]);

        // 3. Validate kết quả từ Python trước khi persist
        $requiredCount = count($fp['sorted_req_skills']);
        $matchedSkills = array_values(array_intersect($fp['sorted_req_skills'], $fp['sorted_cv_skills']));
        $missingSkills = array_values(array_diff($fp['sorted_req_skills'], $fp['sorted_cv_skills']));
        $matchedPrefSkills = array_values(array_intersect($fp['sorted_pref_skills'], $fp['sorted_cv_skills']));
        $missingPrefSkills = array_values(array_diff($fp['sorted_pref_skills'], $fp['sorted_cv_skills']));

        $skillCoverage = $requiredCount > 0
            ? ($aiResponse['skill_coverage'] !== null ? (float) $aiResponse['skill_coverage'] : null)
            : null;

        // 4. Mở DB::transaction ngắn để lưu lịch sử bất biến + chi tiết kỹ năng
        return DB::transaction(function () use (
            $cv,
            $extraction,
            $job,
            $appId,
            $fp,
            $aiResponse,
            $requiredCount,
            $matchedSkills,
            $missingSkills,
            $matchedPrefSkills,
            $missingPrefSkills,
            $skillCoverage
        ) {
            $result = new AIMatchResult();
            $result->cv_id                  = $cv->id;
            $result->cv_extraction_id       = $extraction->id;
            $result->job_id                 = $job->id;
            $result->application_id         = $appId;
            $result->baseline_score         = round((float) $aiResponse['baseline_score'], 2);
            $result->semantic_similarity    = round((float) $aiResponse['semantic_similarity'], 6);
            $result->skill_coverage         = $skillCoverage !== null ? round($skillCoverage, 6) : null;
            $result->match_score            = round((float) $aiResponse['match_score'], 2);
            $result->required_count         = $requiredCount;
            $result->matched_required_count = count($matchedSkills);
            $result->algorithm_version      = (string) ($aiResponse['algorithm_version'] ?? 'hybrid-v1');
            $result->model_name             = (string) ($aiResponse['model_name'] ?? 'unknown');
            $result->cv_text_hash           = $fp['cv_text_hash'];
            $result->job_text_hash          = $fp['job_text_hash'];
            $result->input_fingerprint      = $fp['input_fingerprint'];
            $result->warnings               = json_encode($aiResponse['warnings'] ?? []);
            $result->computed_at            = now();
            $result->save();

            $skillRows = [];
            foreach ($matchedSkills as $skillId) {
                $skillRows[] = [
                    'match_result_id'  => $result->id,
                    'skill_id'         => $skillId,
                    'requirement_type' => 'required',
                    'match_status'     => 'matched',
                ];
            }
            foreach ($missingSkills as $skillId) {
                $skillRows[] = [
                    'match_result_id'  => $result->id,
                    'skill_id'         => $skillId,
                    'requirement_type' => 'required',
                    'match_status'     => 'missing',
                ];
            }
            foreach ($matchedPrefSkills as $skillId) {
                $skillRows[] = [
                    'match_result_id'  => $result->id,
                    'skill_id'         => $skillId,
                    'requirement_type' => 'preferred',
                    'match_status'     => 'matched',
                ];
            }
            foreach ($missingPrefSkills as $skillId) {
                $skillRows[] = [
                    'match_result_id'  => $result->id,
                    'skill_id'         => $skillId,
                    'requirement_type' => 'preferred',
                    'match_status'     => 'missing',
                ];
            }

            if (!empty($skillRows)) {
                AIMatchResultSkill::insert($skillRows);
            }

            return $result->load(['matchResultSkills.skill']);
        });
    }

    /**
     * AI-003 (API-AI-01): Gợi ý việc làm công khai hợp lệ cho Ứng viên theo CV đã confirmed.
     */
    public function recommend(User $actor, int $cvId, array $filters = []): array
    {
        $profile = $actor->candidateProfile;
        $cv = CandidateCV::whereNull('deleted_at')->findOrFail($cvId);

        if (!$profile || (int) $cv->candidate_profile_id !== (int) $profile->id) {
            throw new AuthorizationException('Bạn không có quyền truy cập CV này.');
        }

        $hasConfirmed = CVExtraction::where('cv_id', $cv->id)
            ->where('review_status', 'confirmed')
            ->exists();

        if (!$hasConfirmed) {
            throw new RuntimeException('CV_NOT_CONFIRMED', 409);
        }

        $poolLimit = (int) config('services.ai_service.recommendation_pool_limit', 50);

        // Chỉ xét tập việc làm công khai hợp lệ (Approved-Job Gate)
        $eligibleJobs = Job::with(['company', 'jobSkills'])
            ->where('review_status', 'approved')
            ->where('publication_status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->limit($poolLimit)
            ->get();

        $scoredItems = [];
        foreach ($eligibleJobs as $job) {
            $matchResult = $this->calculate($cv->id, $job->id, null);
            $scoredItems[] = [
                'job'          => $job,
                'match_result' => $matchResult,
                'score_stale'  => false,
            ];
        }

        // Sắp xếp giảm dần theo match_score (tuyệt đối không loại bỏ việc làm điểm thấp)
        usort($scoredItems, function ($a, $b) {
            $cmp = $b['match_result']->match_score <=> $a['match_result']->match_score;
            return $cmp !== 0 ? $cmp : ($b['job']->id <=> $a['job']->id);
        });

        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(50, max(1, (int) ($filters['page_size'] ?? 10)));
        $total = count($scoredItems);
        $slice = array_slice($scoredItems, ($page - 1) * $pageSize, $pageSize);

        return [
            'items'         => $slice,
            'coverage_note' => 'Kết quả trong tập tin được xét',
            'pagination'    => [
                'page'        => $page,
                'page_size'   => $pageSize,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * AI-004 (API-APP-05): Sắp xếp hồ sơ ứng viên của một tin tuyển dụng theo MatchScore giảm dần.
     * Quy tắc BR-AI-07: Không tự động loại ứng viên điểm thấp; hồ sơ chưa có điểm xếp cuối (không gán giả 0).
     */
    public function sortByScore(int $jobId, array $filters = []): LengthAwarePaginator
    {
        $job = Job::with('jobSkills')->findOrFail($jobId);

        $query = Application::with(['candidateProfile.user', 'candidateCV'])
            ->where('job_id', $job->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $applications = $query->get();

        // Tính hoặc nạp điểm match cho từng hồ sơ (nếu chưa có)
        foreach ($applications as $app) {
            try {
                $match = $this->calculate($app->cv_id, $job->id, $app->id);
                $app->setRelation('latestMatchResult', $match);
                $app->setAttribute('score_stale', false);
            } catch (RuntimeException $e) {
                // Nếu AI service tạm thời gián đoạn, giữ nguyên latestMatchResult = null (không gán giả 0)
                $existing = AIMatchResult::with(['matchResultSkills.skill'])
                    ->where('cv_id', $app->cv_id)
                    ->where('job_id', $job->id)
                    ->latest('computed_at')
                    ->latest('id')
                    ->first();

                $app->setRelation('latestMatchResult', $existing);
                $app->setAttribute('score_stale', $existing !== null);
            }
        }

        $sorted = $applications->sort(function (Application $a, Application $b) {
            $scoreA = $a->latestMatchResult?->match_score;
            $scoreB = $b->latestMatchResult?->match_score;

            // Hồ sơ có điểm luôn đứng trước hồ sơ chưa có điểm (null)
            if ($scoreA === null && $scoreB === null) {
                return $b->applied_at <=> $a->applied_at;
            }
            if ($scoreA === null) {
                return 1;
            }
            if ($scoreB === null) {
                return -1;
            }

            $cmp = (float) $scoreB <=> (float) $scoreA;
            return $cmp !== 0 ? $cmp : ($b->applied_at <=> $a->applied_at);
        })->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = min(50, max(1, (int) ($filters['page_size'] ?? 10)));
        $total = $sorted->count();

        return new PaginatorInstance(
            $sorted->forPage($page, $pageSize)->values(),
            $total,
            $pageSize,
            $page
        );
    }

    /**
     * Kiểm tra một bản ghi AIMatchResult có còn khớp fingerprint hiện tại của CV & Job hay không.
     */
    public function isResultStale(AIMatchResult $result, CVExtraction $latestConfirmedExtraction, Job $job): bool
    {
        $confirmedSkillIds = $latestConfirmedExtraction->skills
            ->filter(fn ($skill) => ($skill->pivot->review_status ?? null) === 'confirmed')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $requiredSkillIds = $job->jobSkills
            ->where('requirement_type', 'required')
            ->pluck('skill_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $preferredSkillIds = $job->jobSkills
            ->where('requirement_type', 'preferred')
            ->pluck('skill_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $jdText = trim($job->title . "\n" . $job->description . "\n" . ($job->requirements_text ?? ''));
        $fp = $this->buildFingerprints(
            (string) $latestConfirmedExtraction->confirmed_text,
            $confirmedSkillIds,
            $jdText,
            $requiredSkillIds,
            $preferredSkillIds,
            $result->algorithm_version
        );

        return $result->input_fingerprint !== $fp['input_fingerprint'];
    }

    private function normalizeForHash(string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', mb_strtolower($text, 'UTF-8')) ?? '');
        return $clean;
    }
}
