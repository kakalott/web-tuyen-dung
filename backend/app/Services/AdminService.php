<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
}
