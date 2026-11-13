<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Job;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminService
{
    /**
     * ADM-001 (API-ADM-01): Lấy danh sách doanh nghiệp chờ xác minh (unverified), có phân trang.
     */
    public function listUnverifiedCompanies(int $page = 1, int $pageSize = 15): LengthAwarePaginator
    {
        $safePageSize = max(1, min(100, $pageSize));

        return Company::with(['user:id,name,email'])
            ->where('verification_status', 'unverified')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($safePageSize, ['*'], 'page', max(1, $page));
    }

    /**
     * ADM-001 (API-ADM-02): Xem chi tiết hồ sơ doanh nghiệp phục vụ kiểm tra xác minh.
     */
    public function showCompany(int $companyId): Company
    {
        $company = Company::with([
            'user:id,name,email,account_status',
            'verifier:id,name,email',
        ])->find($companyId);

        if (!$company) {
            throw new ModelNotFoundException('Không tìm thấy doanh nghiệp.');
        }

        return $company;
    }

    /**
     * ADM-001 (API-ADM-03): Xác minh hoặc từ chối/thu hồi xác minh doanh nghiệp.
     * Dùng DB::transaction + SELECT ... FOR UPDATE để khóa bản ghi chống xung đột đồng thời.
     */
    public function verifyCompany(User $admin, int $companyId, array $dto): Company
    {
        if ($admin->role !== 'admin') {
            throw new AccessDeniedHttpException('Chỉ quản trị viên mới có quyền xác minh doanh nghiệp.');
        }

        return DB::transaction(function () use ($admin, $companyId, $dto) {
            $company = Company::where('id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$company) {
                throw new ModelNotFoundException('Không tìm thấy doanh nghiệp.');
            }

            $status = $dto['verification_status'];
            $note = isset($dto['note']) && trim((string) $dto['note']) !== ''
                ? trim((string) $dto['note'])
                : null;

            $company->verification_status = $status;
            $company->verification_note = $note;
            $company->verified_by = $admin->id;
            $company->verified_at = ($status === 'verified') ? now() : null;
            $company->save();

            return $company->fresh(['user:id,name,email', 'verifier:id,name,email']);
        });
    }

    /**
     * ADM-002 (API-ADM-04): Danh sách tin tuyển dụng đang chờ duyệt.
     */
    public function listPendingJobs(array $filters = []): LengthAwarePaginator
    {
        $pageSize = (int) ($filters['page_size'] ?? 10);

        return Job::query()
            ->with([
                'company:id,company_name,verification_status',
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
            ])
            ->where('review_status', 'pending')
            ->orderBy('id')
            ->paginate($pageSize);
    }

    /**
     * ADM-002 (API-ADM-05): Admin xem chi tiết bất kỳ tin tuyển dụng nào (không giới hạn public scope).
     */
    public function showJob(int $jobId): Job
    {
        return Job::query()
            ->with([
                'company:id,user_id,company_name,verification_status',
                'jobGroup:id,name',
                'employmentType:id,name',
                'workMode:id,name',
                'seniorityLevel:id,name',
                'skills:id,name',
            ])
            ->findOrFail($jobId);
    }

    /**
     * ADM-002 (API-ADM-06): Admin duyệt hoặc từ chối tin tuyển dụng.
     */
    public function reviewJob(User $admin, int $jobId, string $decision, ?string $reason = null): Job
    {
        return DB::transaction(function () use ($admin, $jobId, $decision, $reason) {
            /** @var Job $job */
            $job = Job::with('company')
                ->where('id', $jobId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($job->review_status !== 'pending') {
                throw new HttpException(409, 'STATE_CONFLICT: Chỉ có thể kiểm duyệt tin đang ở trạng thái chờ duyệt (pending).');
            }

            if ($decision === 'approve') {
                if (!$job->company || $job->company->verification_status !== 'verified') {
                    throw new HttpException(409, 'STATE_CONFLICT: Không thể duyệt tin vì doanh nghiệp sở hữu chưa được xác minh.');
                }

                if ($job->expires_at !== null && $job->expires_at->lte(now())) {
                    throw new HttpException(409, 'STATE_CONFLICT: Không thể duyệt tin đã quá hạn nộp hồ sơ.');
                }

                $job->review_status      = 'approved';
                $job->publication_status = 'active';
                $job->rejection_reason   = null;
                $job->reviewed_by        = $admin->id;
                $job->reviewed_at        = now();
                $job->save();

                Notification::create([
                    'user_id'      => $job->company->user_id,
                    'event_type'   => 'job.approved',
                    'content'      => "Tin tuyển dụng \"{$job->title}\" của bạn đã được phê duyệt và hiển thị công khai.",
                    'related_type' => 'job',
                    'related_id'   => $job->id,
                    'created_at'   => now(),
                ]);
            } else {
                $cleanReason = trim((string) $reason);
                if ($cleanReason === '') {
                    throw new HttpException(422, 'Vui lòng nhập lý do từ chối tin tuyển dụng.');
                }

                $job->review_status      = 'rejected';
                $job->publication_status = 'not_public';
                $job->rejection_reason   = $cleanReason;
                $job->reviewed_by        = $admin->id;
                $job->reviewed_at        = now();
                $job->save();

                Notification::create([
                    'user_id'      => $job->company->user_id,
                    'event_type'   => 'job.rejected',
                    'content'      => "Tin tuyển dụng \"{$job->title}\" bị từ chối. Lý do: {$cleanReason}",
                    'related_type' => 'job',
                    'related_id'   => $job->id,
                    'created_at'   => now(),
                ]);
            }

            return $job->fresh();
        });
    }

    /**
     * ADM-002 (API-ADM-07): Admin ẩn tin tuyển dụng vi phạm đang công khai.
     */
    public function hideJob(User $admin, int $jobId, string $reason): Job
    {
        return DB::transaction(function () use ($jobId, $reason) {
            /** @var Job $job */
            $job = Job::with('company')
                ->where('id', $jobId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($job->publication_status !== 'active') {
                throw new HttpException(409, 'STATE_CONFLICT: Chỉ có thể ẩn tin đang ở trạng thái hoạt động (active).');
            }

            $cleanReason = trim($reason);
            $job->publication_status = 'hidden';
            $job->hidden_reason      = $cleanReason;
            $job->save();

            if ($job->company) {
                Notification::create([
                    'user_id'      => $job->company->user_id,
                    'event_type'   => 'job.hidden',
                    'content'      => "Tin tuyển dụng \"{$job->title}\" đã bị quản trị viên ẩn. Lý do: {$cleanReason}",
                    'related_type' => 'job',
                    'related_id'   => $job->id,
                    'created_at'   => now(),
                ]);
            }

            return $job->fresh();
        });
    }
}
