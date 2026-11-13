<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use App\Support\ApiError;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkillService
{
    /**
     * API-SKL-01: Tra cứu danh mục kỹ năng chuẩn đang hoạt động (is_active = 1).
     * Hỗ trợ tìm kiếm an toàn qua cả tên chuẩn và alias, trả về distinct theo skills.id.
     */
    public function index(array $filters): LengthAwarePaginator|Collection
    {
        $query = Skill::query()
            ->select(['skills.id', 'skills.name'])
            ->where('skills.is_active', true);

        if (! empty($filters['search'])) {
            $rawSearch = trim((string) $filters['search']);
            $normalized = mb_strtolower($rawSearch, 'UTF-8');
            // Escape ký tự wildcard SQL LIKE (% và _)
            $escaped = addcslashes($normalized, '%_\\');
            $pattern = '%' . $escaped . '%';

            $query->where(function ($q) use ($pattern) {
                $q->where('skills.normalized_name', 'like', $pattern)
                    ->orWhereExists(function ($subQuery) use ($pattern) {
                        $subQuery->select(DB::raw(1))
                            ->from('skill_aliases')
                            ->whereColumn('skill_aliases.skill_id', 'skills.id')
                            ->where('skill_aliases.is_active', true)
                            ->where('skill_aliases.normalized_alias', 'like', $pattern);
                    });
            });
        }

        $query->distinct()->orderBy('skills.name');

        if (isset($filters['page']) || isset($filters['page_size'])) {
            $pageSize = max(1, min(100, (int) ($filters['page_size'] ?? 20)));
            return $query->paginate($pageSize);
        }

        return $query->get();
    }

    /**
     * API-SKL-02: Lấy danh sách kỹ năng đã khai báo của ứng viên (bao gồm cả mục cũ đã inactive).
     */
    public function listCandidateSkills(User $actor): Collection
    {
        $profile = CandidateProfile::where('user_id', $actor->id)->first();

        if (! $profile) {
            throw ApiError::notFound('Hồ sơ ứng viên không tồn tại.');
        }

        return DB::table('candidate_skills')
            ->join('skills', 'skills.id', '=', 'candidate_skills.skill_id')
            ->where('candidate_skills.candidate_profile_id', $profile->id)
            ->orderBy('skills.name')
            ->get([
                'skills.id',
                'skills.name',
                'candidate_skills.source',
            ]);
    }

    /**
     * API-SKL-03: Đồng bộ danh sách kỹ năng cá nhân (source = manual) một cách nguyên tử.
     */
    public function syncCandidate(User $actor, array $skillIds): Collection
    {
        return DB::transaction(function () use ($actor, $skillIds) {
            $profile = CandidateProfile::where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                throw ApiError::notFound('Hồ sơ ứng viên không tồn tại.');
            }

            $uniqueIds = array_values(array_unique(array_map('intval', $skillIds)));
            $now = now();

            $syncPayload = [];
            foreach ($uniqueIds as $skillId) {
                $syncPayload[$skillId] = [
                    'source'       => 'manual',
                    'confirmed_at' => $now,
                ];
            }

            // Đồng bộ pivot candidate_skills; không tác động tới cv_extraction_skills
            $profile->skills()->sync($syncPayload);

            return collect($uniqueIds);
        });
    }
}
