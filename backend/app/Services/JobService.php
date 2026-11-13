<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobSkill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JobService
{
    /**
     * Scope cứng cho mọi truy vấn tin tuyển dụng công khai (BR-JOB-02, BR-JOB-06).
     */
    private function applyPublicScope(Builder $query): Builder
    {
        return $query
            ->where('review_status', 'approved')
            ->where('publication_status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * JOB-001 (API-JOB-01): Tìm kiếm và lọc tin tuyển dụng công khai.
     */
    public function publicList(array $filters): LengthAwarePaginator
    {
        $query = Job::query()
            ->with([
                'company:id,company_name,website_url',
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
                'skills:id,name',
            ]);

        $this->applyPublicScope($query);

        if (!empty($filters['keyword'])) {
            $escaped = addcslashes(trim((string) $filters['keyword']), '%_\\');
            $query->where(function (Builder $q) use ($escaped) {
                $q->where('title', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        foreach (['job_group_id', 'employment_type_id', 'work_mode_id', 'seniority_level_id'] as $filterCol) {
            if (!empty($filters[$filterCol])) {
                $query->where($filterCol, (int) $filters[$filterCol]);
            }
        }

        $pageSize = (int) ($filters['page_size'] ?? 10);

        return $query->orderByDesc('id')->paginate($pageSize);
    }

    /**
     * JOB-002 (API-JOB-02): Xem chi tiết tin tuyển dụng công khai.
     * Trả 404 nếu tin không thỏa đồng thời 3 điều kiện công khai.
     */
    public function publicShow(int $jobId): Job
    {
        $query = Job::query()
            ->with([
                'company:id,company_name,description,website_url',
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
                'skills:id,name',
            ])
            ->where('id', $jobId);

        $job = $this->applyPublicScope($query)->first();

        if (!$job) {
            throw new ModelNotFoundException('Tin tuyển dụng không tồn tại hoặc không còn công khai.');
        }

        return $job;
    }

    /**
     * JOB-006 (API-JOB-03): Danh sách tin tuyển dụng của doanh nghiệp hiện tại.
     */
    public function companyList(User $actor, array $filters): LengthAwarePaginator
    {
        $company = Company::where('user_id', $actor->id)->firstOrFail();

        $query = Job::query()
            ->with([
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
                'skills:id,name',
            ])
            ->where('company_id', $company->id);

        if (!empty($filters['review_status'])) {
            $query->where('review_status', $filters['review_status']);
        }

        if (!empty($filters['publication_status'])) {
            $query->where('publication_status', $filters['publication_status']);
        }

        $pageSize = (int) ($filters['page_size'] ?? 10);

        return $query->orderByDesc('id')->paginate($pageSize);
    }

    /**
     * JOB-006 (API-JOB-05): Xem chi tiết tin nội bộ của doanh nghiệp (kèm lý do duyệt/ẩn).
     */
    public function companyShow(User $actor, Job $job): Job
    {
        return $job->load([
            'company:id,company_name,verification_status',
            'jobGroup:id,name',
            'employmentType:id,name',
            'workMode:id,name',
            'seniorityLevel:id,name',
            'skills:id,name',
        ]);
    }

    /**
     * JOB-003 (API-JOB-04): Doanh nghiệp verified tạo tin tuyển dụng mới.
     */
    public function store(User $actor, array $dto): Job
    {
        return DB::transaction(function () use ($actor, $dto) {
            $company = Company::where('user_id', $actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($company->verification_status !== 'verified') {
                throw new HttpException(403, 'Doanh nghiệp chưa được xác minh nên không thể tạo tin tuyển dụng.');
            }

            $job = new Job();
            $job->company_id         = $company->id;
            $job->title              = trim($dto['title']);
            $job->description        = trim($dto['description']);
            $job->requirements_text  = trim($dto['requirements_text']);
            $job->job_group_id       = (int) $dto['job_group_id'];
            $job->employment_type_id = (int) $dto['employment_type_id'];
            $job->work_mode_id       = (int) $dto['work_mode_id'];
            $job->seniority_level_id = (int) $dto['seniority_level_id'];
            $job->expires_at         = !empty($dto['expires_at']) ? Carbon::parse($dto['expires_at']) : null;
            $job->review_status      = 'pending';
            $job->publication_status = 'not_public';
            $job->save();

            $this->syncJobSkills(
                $job->id,
                $dto['required_skill_ids'] ?? [],
                $dto['preferred_skill_ids'] ?? []
            );

            return $job->load([
                'company:id,company_name,verification_status',
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
                'skills:id,name',
            ]);
        });
    }

    /**
     * JOB-004 (API-JOB-06): Cập nhật tin tuyển dụng.
     * Nếu tin đã approved (hoặc rejected) và có thay đổi nội dung chính -> đưa về pending / not_public.
     */
    public function update(User $actor, Job $job, array $dto): Job
    {
        return DB::transaction(function () use ($actor, $job, $dto) {
            $company = Company::where('user_id', $actor->id)->firstOrFail();
            if ($company->verification_status !== 'verified') {
                throw new HttpException(403, 'Doanh nghiệp chưa được xác minh.');
            }

            /** @var Job $lockedJob */
            $lockedJob = Job::where('id', $job->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Lấy danh sách kỹ năng hiện tại trước khi cập nhật để so sánh
            $oldRequiredIds = JobSkill::where('job_id', $lockedJob->id)
                ->where('requirement_type', 'required')
                ->pluck('skill_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $oldPreferredIds = JobSkill::where('job_id', $lockedJob->id)
                ->where('requirement_type', 'preferred')
                ->pluck('skill_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $newRequiredIds = collect($dto['required_skill_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $newPreferredIds = collect($dto['preferred_skill_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $newTitle        = trim($dto['title']);
            $newDescription  = trim($dto['description']);
            $newRequirements = trim($dto['requirements_text']);
            $newJobGroupId   = (int) $dto['job_group_id'];
            $newEmpTypeId    = (int) $dto['employment_type_id'];
            $newWorkModeId   = (int) $dto['work_mode_id'];
            $newSeniorityId  = (int) $dto['seniority_level_id'];
            $newExpiresAt    = !empty($dto['expires_at']) ? Carbon::parse($dto['expires_at']) : null;

            $oldExpiresTimestamp = $lockedJob->expires_at?->timestamp;
            $newExpiresTimestamp = $newExpiresAt?->timestamp;

            $mainFieldsChanged =
                $lockedJob->title !== $newTitle ||
                $lockedJob->description !== $newDescription ||
                $lockedJob->requirements_text !== $newRequirements ||
                (int) $lockedJob->job_group_id !== $newJobGroupId ||
                (int) $lockedJob->employment_type_id !== $newEmpTypeId ||
                (int) $lockedJob->work_mode_id !== $newWorkModeId ||
                (int) $lockedJob->seniority_level_id !== $newSeniorityId ||
                $oldExpiresTimestamp !== $newExpiresTimestamp ||
                $oldRequiredIds !== $newRequiredIds ||
                $oldPreferredIds !== $newPreferredIds;

            $lockedJob->title              = $newTitle;
            $lockedJob->description        = $newDescription;
            $lockedJob->requirements_text  = $newRequirements;
            $lockedJob->job_group_id       = $newJobGroupId;
            $lockedJob->employment_type_id = $newEmpTypeId;
            $lockedJob->work_mode_id       = $newWorkModeId;
            $lockedJob->seniority_level_id = $newSeniorityId;
            $lockedJob->expires_at         = $newExpiresAt;

            // BR-JOB-04 & DEC-REVIEW: Thay đổi nội dung chính sau khi duyệt/từ chối -> reset về chờ duyệt
            if ($mainFieldsChanged && in_array($lockedJob->review_status, ['approved', 'rejected'], true)) {
                $lockedJob->review_status      = 'pending';
                $lockedJob->publication_status = 'not_public';
                $lockedJob->rejection_reason   = null;
                $lockedJob->reviewed_by        = null;
                $lockedJob->reviewed_at        = null;
            }

            $lockedJob->save();

            $this->syncJobSkills($lockedJob->id, $newRequiredIds, $newPreferredIds);

            return $lockedJob->fresh()->load([
                'company:id,company_name,verification_status',
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
                'skills:id,name',
            ]);
        });
    }

    /**
     * JOB-005 (API-JOB-07): Đóng tin tuyển dụng đang hoạt động.
     */
    public function close(User $actor, Job $job): Job
    {
        return DB::transaction(function () use ($job) {
            /** @var Job $lockedJob */
            $lockedJob = Job::where('id', $job->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedJob->publication_status !== 'active') {
                throw new HttpException(409, 'STATE_CONFLICT: Chỉ có thể đóng tin đang ở trạng thái hoạt động (active).');
            }

            $lockedJob->publication_status = 'closed';
            $lockedJob->save();

            return $lockedJob->fresh();
        });
    }

    /**
     * Đồng bộ kỹ năng bắt buộc và ưu tiên vào bảng job_skills.
     */
    private function syncJobSkills(int $jobId, array $requiredIds, array $preferredIds): void
    {
        JobSkill::where('job_id', $jobId)->delete();

        $rows = [];

        foreach (array_unique($requiredIds) as $skillId) {
            $rows[] = [
                'job_id'           => $jobId,
                'skill_id'         => (int) $skillId,
                'requirement_type' => 'required',
            ];
        }

        foreach (array_unique($preferredIds) as $skillId) {
            $rows[] = [
                'job_id'           => $jobId,
                'skill_id'         => (int) $skillId,
                'requirement_type' => 'preferred',
            ];
        }

        if (!empty($rows)) {
            JobSkill::insert($rows);
        }
    }
}
