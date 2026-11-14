<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateCV;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Notification;
use App\Models\User;
use App\Models\AIMatchResult;
use App\Models\CVExtraction;
use App\Support\DomainStates;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApplicationService
{
    public function __construct(private ?MatchingService $matchingService = null)
    {
    }
    /**
     * APP-001 (API-APP-01): Ứng viên nộp CV vào tin tuyển dụng hợp lệ.
     */
    public function apply(User $actor, int $jobId, int $cvId): Application
    {
        $candidateProfile = $actor->candidateProfile;
        if (!$candidateProfile) {
            throw new HttpException(403, 'Tài khoản chưa có hồ sơ ứng viên.');
        }

        try {
            return DB::transaction(function () use ($actor, $candidateProfile, $jobId, $cvId) {
                // 1. Khóa dòng Job để đảm bảo tin không bị đóng/ẩn/hết hạn đồng thời khi đang nộp
                $job = Job::with('company')->where('id', $jobId)->lockForUpdate()->first();
                if (!$job) {
                    throw new ModelNotFoundException('Không tìm thấy tin tuyển dụng.');
                }

                if (!DomainStates::isPublicJob($job)) {
                    throw new HttpException(409, 'Tin tuyển dụng này hiện không nhận hồ sơ (chưa duyệt, đã đóng, bị ẩn hoặc đã hết hạn).');
                }

                // 2. Kiểm tra CV thuộc ứng viên, chưa bị xóa và đã xác nhận extraction (BR-APP-01)
                $cv = CandidateCV::with('latestExtraction')
                    ->where('id', $cvId)
                    ->where('candidate_profile_id', $candidateProfile->id)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$cv) {
                    throw new HttpException(422, 'CV không tồn tại hoặc không thuộc quyền sở hữu của bạn.');
                }

                $hasConfirmedExtraction = $cv->extractions()
                    ->where('review_status', 'confirmed')
                    ->exists();

                if (!$hasConfirmedExtraction) {
                    throw new HttpException(409, 'CV phải được xác nhận nội dung trích xuất trước khi dùng để ứng tuyển.');
                }

                // 3. Kiểm tra nộp trùng theo quyết định D-01 (BR-APP-02)
                $alreadyApplied = Application::where('candidate_profile_id', $candidateProfile->id)
                    ->where('job_id', $job->id)
                    ->exists();

                if ($alreadyApplied) {
                    throw new HttpException(409, 'Bạn đã nộp hồ sơ cho vị trí tuyển dụng này trước đó.');
                }

                $now = now();

                // 4. Tạo đơn ứng tuyển với trạng thái khởi tạo pending (BR-APP-03)
                $application = new Application();
                $application->candidate_profile_id = $candidateProfile->id;
                $application->job_id               = $job->id;
                $application->cv_id                = $cv->id;
                $application->status               = 'pending';
                $application->applied_at           = $now;
                $application->save();

                // 5. Ghi lịch sử trạng thái đầu tiên (from_status = null -> pending)
                ApplicationStatusHistory::create([
                    'application_id'     => $application->id,
                    'from_status'        => null,
                    'to_status'          => 'pending',
                    'changed_by_user_id' => $actor->id,
                    'note'               => null,
                    'changed_at'         => $now,
                ]);

                // 6. Tạo notification website cho chủ doanh nghiệp trong cùng transaction (BR-NOTI-01)
                if ($job->company && $job->company->user_id) {
                    Notification::create([
                        'user_id'      => $job->company->user_id,
                        'event_type'   => 'application.created',
                        'content'      => "Ứng viên {$actor->name} vừa nộp hồ sơ vào tin \"{$job->title}\".",
                        'related_type' => 'application',
                        'related_id'   => $application->id,
                        'created_at'   => $now,
                    ]);
                }

                return $application->load(['job.company', 'cv', 'statusHistories']);
            });
        } catch (QueryException $e) {
            // Chốt chặn cuối cùng từ UNIQUE(candidate_profile_id, job_id) khi 2 request đua nhau (Mục 4.16)
            if ($e->getCode() === '23000') {
                throw new HttpException(409, 'Bạn đã nộp hồ sơ cho vị trí tuyển dụng này trước đó.');
            }
            throw $e;
        }
    }

    /**
     * APP-002 (API-APP-02): Danh sách đơn ứng tuyển của ứng viên hiện hành.
     */
    public function listForCandidate(User $actor, array $filters): LengthAwarePaginator
    {
        $candidateProfileId = $actor->candidateProfile->id;
        $pageSize = (int) ($filters['page_size'] ?? 15);

        $query = Application::with(['job.company'])
            ->where('candidate_profile_id', $candidateProfileId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->paginate($pageSize);
    }

    /**
     * APP-002 (API-APP-03): Chi tiết đơn ứng tuyển của ứng viên.
     */
    public function detailForCandidate(Application $application): Application
    {
        return $application->load([
            'job.company',
            'cv',
            'statusHistories' => fn ($q) => $q->orderBy('changed_at')->orderBy('id'),
            'interviews'      => fn ($q) => $q->orderBy('scheduled_at')->orderBy('id'),
        ]);
    }

    /**
     * APP-003 (API-APP-04): Ứng viên rút hồ sơ trước khi đạt trạng thái kết thúc.
     */
    public function withdraw(User $actor, int $id): Application
    {
        return DB::transaction(function () use ($actor, $id) {
            // 1. Khóa dòng application chống race condition với thao tác đổi trạng thái của DN
            $application = Application::with(['job.company'])
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$application) {
                throw new ModelNotFoundException('Không tìm thấy đơn ứng tuyển.');
            }

            if (!$actor->candidateProfile || $application->candidate_profile_id !== $actor->candidateProfile->id) {
                throw new HttpException(403, 'Bạn không có quyền thao tác trên đơn ứng tuyển này.');
            }

            // 2. Kiểm tra điều kiện rút đơn (BR-APP-04, BR-APP-09)
            if (!DomainStates::canCandidateWithdraw($application->status)) {
                throw new HttpException(409, 'Không thể rút hồ sơ khi đơn đã ở trạng thái kết thúc.');
            }

            $oldStatus = $application->status;
            $now = now();

            // 3. Cập nhật trạng thái sang withdrawn
            $application->status = 'withdrawn';
            $application->withdrawn_at = $now;
            $application->save();

            // 4. Ghi lịch sử chuyển trạng thái
            ApplicationStatusHistory::create([
                'application_id'     => $application->id,
                'from_status'        => $oldStatus,
                'to_status'          => 'withdrawn',
                'changed_by_user_id' => $actor->id,
                'note'               => 'Ứng viên chủ động rút hồ sơ.',
                'changed_at'         => $now,
            ]);

            // 5. Hủy toàn bộ lịch phỏng vấn đang scheduled của đơn này (API-APP-04)
            Interview::where('application_id', $application->id)
                ->where('status', 'scheduled')
                ->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => 'Ứng viên đã rút hồ sơ',
                    'updated_at'    => $now,
                ]);

            // 6. Tạo notification thông báo cho doanh nghiệp
            if ($application->job && $application->job->company && $application->job->company->user_id) {
                Notification::create([
                    'user_id'      => $application->job->company->user_id,
                    'event_type'   => 'application.withdrawn',
                    'content'      => "Ứng viên {$actor->name} đã rút hồ sơ khỏi tin \"{$application->job->title}\".",
                    'related_type' => 'application',
                    'related_id'   => $application->id,
                    'created_at'   => $now,
                ]);
            }

            return $application->fresh(['job.company', 'statusHistories', 'interviews']);
        });
    }

    /**
     * APP-004 (API-APP-05): Danh sách hồ sơ nộp vào tin tuyển dụng của doanh nghiệp.
     */
    public function listForJob(Job $job, array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'applied_desc';

        if ($sort === 'match_desc' && $this->matchingService) {
            return $this->matchingService->sortByScore($job->id, $filters);
        }

        $pageSize = min(50, max(1, (int) ($filters['page_size'] ?? 15)));
        $query = Application::query()
            ->with(['candidateProfile.user', 'cv', 'latestMatchResult'])
            ->where('job_id', $job->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->paginate($pageSize);

        // Gắn điểm match đã lưu (nếu có) và cờ score_stale cho từng hồ sơ
        $paginator->getCollection()->transform(function (Application $app) use ($job) {
            $latestMatch = AIMatchResult::with(['matchResultSkills.skill'])
                ->where('cv_id', $app->cv_id)
                ->where('job_id', $job->id)
                ->latest('computed_at')
                ->latest('id')
                ->first();

            $app->setRelation('latestMatchResult', $latestMatch);

            if ($latestMatch && $this->matchingService) {
                $latestExtraction = CVExtraction::with('skills')
                    ->where('cv_id', $app->cv_id)
                    ->where('review_status', 'confirmed')
                    ->latest('confirmed_at')
                    ->first();

                $isStale = $latestExtraction
                    ? $this->matchingService->isResultStale($latestMatch, $latestExtraction, $job)
                    : true;
                $app->setAttribute('score_stale', $isStale);
            } else {
                $app->setAttribute('score_stale', false);
            }

            return $app;
        });

        return $paginator;
    }

    /**
     * APP-004 (API-APP-06): Doanh nghiệp xem chi tiết hồ sơ ứng tuyển.
     */
    public function detailForCompany(Application $application): Application
    {
        return $application->load([
            'candidateProfile.user',
            'candidateProfile.educations',
            'candidateProfile.experiences',
            'candidateProfile.skills',
            'job',
            'cv.latestExtraction.skills',
            'statusHistories' => fn ($q) => $q->orderBy('changed_at')->orderBy('id'),
            'interviews'      => fn ($q) => $q->orderBy('scheduled_at')->orderBy('id'),
            'latestMatchResult.matchResultSkills.skill',
        ]);
    }

    /**
     * APP-005 (API-APP-08): Doanh nghiệp cập nhật trạng thái đơn ứng tuyển theo ma trận.
     */
    public function changeStatus(User $actor, int $id, string $next, ?string $note = null): Application
    {
        return DB::transaction(function () use ($actor, $id, $next, $note) {
            // 1. Khóa dòng application trước khi kiểm tra trạng thái
            $application = Application::with(['job.company', 'candidateProfile'])
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$application) {
                throw new ModelNotFoundException('Không tìm thấy đơn ứng tuyển.');
            }

            // Kiểm tra quyền sở hữu công ty đã xác minh
            if (
                !$actor->company
                || $actor->company->verification_status !== 'verified'
                || !$application->job
                || $application->job->company_id !== $actor->company->id
            ) {
                throw new HttpException(403, 'Bạn không có quyền cập nhật trạng thái đơn ứng tuyển này.');
            }

            // 2. Kiểm tra lịch phỏng vấn scheduled còn hiệu lực (ràng buộc interview -> reviewing)
            $hasActiveSchedule = Interview::where('application_id', $application->id)
                ->where('status', 'scheduled')
                ->exists();

            // 3. Đối chiếu ma trận chuyển tiếp trạng thái (BR-APP-06..09)
            if (!DomainStates::canCompanyTransitionApplication($application->status, $next, $hasActiveSchedule)) {
                if ($application->status === $next) {
                    return $application->fresh(['candidateProfile.user', 'job', 'statusHistories', 'interviews']);
                }
                throw new HttpException(409, "Không thể chuyển trạng thái hồ sơ từ '{$application->status}' sang '{$next}'.");
            }

            $oldStatus = $application->status;
            $now = now();

            // 4. Cập nhật trạng thái mới
            $application->status = $next;
            $application->save();

            // 5. Ghi lịch sử thay đổi trạng thái
            ApplicationStatusHistory::create([
                'application_id'     => $application->id,
                'from_status'        => $oldStatus,
                'to_status'          => $next,
                'changed_by_user_id' => $actor->id,
                'note'               => $note !== null ? trim($note) : null,
                'changed_at'         => $now,
            ]);

            // 6. Tạo notification website cho ứng viên trong cùng transaction
            if ($application->candidateProfile && $application->candidateProfile->user_id) {
                Notification::create([
                    'user_id'      => $application->candidateProfile->user_id,
                    'event_type'   => 'application.status_changed',
                    'content'      => "Hồ sơ ứng tuyển vị trí \"{$application->job->title}\" đã chuyển sang trạng thái: {$next}.",
                    'related_type' => 'application',
                    'related_id'   => $application->id,
                    'created_at'   => $now,
                ]);
            }

            return $application->fresh(['candidateProfile.user', 'job', 'statusHistories', 'interviews']);
        });
    }
}
