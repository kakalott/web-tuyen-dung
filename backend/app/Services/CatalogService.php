<?php

namespace App\Services;

use App\Models\EmploymentType;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\WorkMode;

class CatalogService
{
    /**
     * CAT-002 (API-CAT-06): Lấy đồng thời 4 danh mục bộ lọc việc làm đang active.
     */
    public function publicLookups(): array
    {
        return [
            'job_groups' => JobGroup::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'employment_types' => EmploymentType::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'work_modes' => WorkMode::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'seniority_levels' => SeniorityLevel::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
