<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    /**
     * COM-001 (API-COM-01): Xem hồ sơ doanh nghiệp và trạng thái xác minh hiện tại.
     */
    public function show(User $actor): Company
    {
        $company = Company::where('user_id', $actor->id)->first();

        if (!$company) {
            throw new ModelNotFoundException('Không tìm thấy hồ sơ doanh nghiệp.');
        }

        return $company;
    }

    /**
     * COM-001 (API-COM-02): Cập nhật thông tin hồ sơ doanh nghiệp phục vụ xác minh.
     * Chỉ cập nhật whitelist: company_name, description, website_url.
     */
    public function update(User $actor, array $dto): Company
    {
        return DB::transaction(function () use ($actor, $dto) {
            $company = Company::where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (!$company) {
                throw new ModelNotFoundException('Không tìm thấy hồ sơ doanh nghiệp.');
            }

            // Gán tường minh từng trường thuộc whitelist, bỏ qua hoàn toàn các trường nhạy cảm
            $company->company_name = trim($dto['company_name']);

            if (array_key_exists('description', $dto)) {
                $company->description = $dto['description'] !== null
                    ? trim((string) $dto['description'])
                    : null;
            }

            if (array_key_exists('website_url', $dto)) {
                $company->website_url = $dto['website_url'] !== null
                    ? trim((string) $dto['website_url'])
                    : null;
            }

            $company->save();

            return $company->fresh();
        });
    }
}
