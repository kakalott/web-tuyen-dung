<?php

namespace App\Services;

use App\Models\EmploymentType;
use App\Models\JobGroup;
use App\Models\SeniorityLevel;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\WorkMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CatalogService
{
    /**
     * Whitelist map cứng chống SQL Injection trên tham số động {catalog} (API-CAT-07..09).
     */
    private const LOOKUP_MODELS = [
        'job-groups'       => JobGroup::class,
        'employment-types' => EmploymentType::class,
        'work-modes'       => WorkMode::class,
        'seniority-levels' => SeniorityLevel::class,
    ];

    /**
     * CAT-002 (API-CAT-06): Lấy đồng thời 4 danh mục bộ lọc việc làm đang active (từ Phase 07).
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

    /**
     * Chuẩn hóa tên kỹ năng / alias: Unicode NFC, trim, gộp khoảng trắng, lowercase.
     */
    public function normalizeSkill(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        if (class_exists(\Normalizer::class)) {
            $nfc = \Normalizer::normalize($normalized, \Normalizer::FORM_C);
            if ($nfc !== false) {
                $normalized = $nfc;
            }
        }

        return mb_strtolower($normalized, 'UTF-8');
    }

    /**
     * CAT-001 (API-CAT-01): Liệt kê kỹ năng chuẩn (gồm cả inactive) kèm aliases và số lần tham chiếu.
     */
    public function skillsIndex(array $filters = []): LengthAwarePaginator
    {
        $pageSize = max(1, min(100, (int) ($filters['page_size'] ?? 15)));
        $search   = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $query = Skill::query()
            ->with(['aliases' => fn ($q) => $q->orderBy('alias')])
            ->orderBy('name');

        if ($search !== '') {
            $escaped = addcslashes($this->normalizeSkill($search), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('normalized_name', 'like', "%{$escaped}%")
                    ->orWhereHas('aliases', function ($aq) use ($escaped) {
                        $aq->where('normalized_alias', 'like', "%{$escaped}%");
                    });
            });
        }

        return $query->paginate($pageSize);
    }

    /**
     * CAT-001 (API-CAT-02 & API-CAT-03): Tạo mới hoặc cập nhật kỹ năng chuẩn.
     * Kiểm tra xung đột toàn cục trên cả skills.normalized_name và skill_aliases.normalized_alias.
     */
    public function saveSkill(array $dto, ?int $id = null): Skill
    {
        return DB::transaction(function () use ($dto, $id) {
            if ($id === null) {
                $name = (string) $dto['name'];
                $normalized = $this->normalizeSkill($name);

                $this->assertNoGlobalSkillCollision($normalized);

                $skill = Skill::create([
                    'name'            => $name,
                    'normalized_name' => $normalized,
                    'is_active'       => true,
                ]);

                return $skill->load('aliases');
            }

            $skill = Skill::where('id', $id)->lockForUpdate()->first();
            if (!$skill) {
                throw new ModelNotFoundException('Không tìm thấy kỹ năng.');
            }

            if (array_key_exists('name', $dto)) {
                $name = (string) $dto['name'];
                $normalized = $this->normalizeSkill($name);

                $this->assertNoGlobalSkillCollision($normalized, ignoreSkillId: $skill->id);

                $skill->name = $name;
                $skill->normalized_name = $normalized;
            }

            if (array_key_exists('is_active', $dto)) {
                $skill->is_active = (bool) $dto['is_active'];
            }

            $skill->save();

            return $skill->fresh(['aliases']);
        });
    }

    /**
     * CAT-001 (API-CAT-04): Thêm biến thể (alias) cho kỹ năng chuẩn.
     * Kiểm tra xung đột toàn cục trên cả skills và skill_aliases; không tự chạy lại CV cũ.
     */
    public function saveAlias(int $skillId, array $dto): SkillAlias
    {
        return DB::transaction(function () use ($skillId, $dto) {
            $skill = Skill::where('id', $skillId)->lockForUpdate()->first();
            if (!$skill) {
                throw new ModelNotFoundException('Không tìm thấy kỹ năng chuẩn.');
            }

            $rawAlias = (string) ($dto['alias'] ?? $dto['alias_name'] ?? '');
            $normalized = $this->normalizeSkill($rawAlias);

            $this->assertNoGlobalSkillCollision($normalized);

            return SkillAlias::create([
                'skill_id'         => $skill->id,
                'alias'            => $rawAlias,
                'normalized_alias' => $normalized,
                'is_active'        => true,
            ]);
        });
    }

    /**
     * CAT-001 (API-CAT-05): Cập nhật tên biến thể hoặc bật/tắt is_active; giữ nguyên skill_id.
     */
    public function updateAlias(int $aliasId, array $dto): SkillAlias
    {
        return DB::transaction(function () use ($aliasId, $dto) {
            $alias = SkillAlias::where('id', $aliasId)->lockForUpdate()->first();
            if (!$alias) {
                throw new ModelNotFoundException('Không tìm thấy biến thể kỹ năng.');
            }

            $rawAlias = $dto['alias'] ?? $dto['alias_name'] ?? null;
            if ($rawAlias !== null) {
                $rawAlias = (string) $rawAlias;
                $normalized = $this->normalizeSkill($rawAlias);

                $this->assertNoGlobalSkillCollision($normalized, ignoreAliasId: $alias->id);

                $alias->alias = $rawAlias;
                $alias->normalized_alias = $normalized;
            }

            if (array_key_exists('is_active', $dto)) {
                $alias->is_active = (bool) $dto['is_active'];
            }

            $alias->save();

            return $alias->fresh();
        });
    }

    /**
     * Kiểm tra va chạm toàn cục: chuỗi chuẩn hóa không được trùng với bất kỳ
     * skills.normalized_name hay skill_aliases.normalized_alias nào khác.
     */
    private function assertNoGlobalSkillCollision(
        string $normalized,
        ?int $ignoreSkillId = null,
        ?int $ignoreAliasId = null
    ): void {
        if ($normalized === '') {
            throw new HttpException(422, 'Tên kỹ năng hoặc biến thể không hợp lệ sau chuẩn hóa.');
        }

        $skillExists = Skill::query()
            ->where('normalized_name', $normalized)
            ->when($ignoreSkillId !== null, fn ($q) => $q->where('id', '!=', $ignoreSkillId))
            ->exists();

        if ($skillExists) {
            throw new HttpException(409, 'Tên kỹ năng hoặc biến thể đã tồn tại dưới dạng kỹ năng chuẩn.');
        }

        $aliasExists = SkillAlias::query()
            ->where('normalized_alias', $normalized)
            ->when($ignoreAliasId !== null, fn ($q) => $q->where('id', '!=', $ignoreAliasId))
            ->exists();

        if ($aliasExists) {
            throw new HttpException(409, 'Tên kỹ năng hoặc biến thể đã tồn tại dưới dạng biến thể (alias).');
        }
    }

    /**
     * Giải mã slug danh mục sang class Eloquent Model qua whitelist cứng.
     *
     * @return class-string<Model>
     */
    public function lookupModel(string $catalogSlug): string
    {
        if (!array_key_exists($catalogSlug, self::LOOKUP_MODELS)) {
            throw new ModelNotFoundException("Danh mục '{$catalogSlug}' không tồn tại.");
        }

        return self::LOOKUP_MODELS[$catalogSlug];
    }

    /**
     * CAT-002 (API-CAT-07): Liệt kê các mục của một danh mục (gồm cả inactive), có phân trang.
     */
    public function lookupIndex(string $catalogSlug, array $filters = []): LengthAwarePaginator
    {
        $modelClass = $this->lookupModel($catalogSlug);
        $pageSize   = max(1, min(100, (int) ($filters['page_size'] ?? 15)));

        return $modelClass::query()
            ->orderBy('name')
            ->paginate($pageSize);
    }

    /**
     * CAT-002 (API-CAT-08): Tạo mới mục danh mục với is_active = true, chống trùng tên.
     */
    public function lookupStore(string $catalogSlug, array $dto): Model
    {
        $modelClass = $this->lookupModel($catalogSlug);

        return DB::transaction(function () use ($modelClass, $dto) {
            $name = (string) $dto['name'];

            $duplicate = $modelClass::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])
                ->exists();

            if ($duplicate) {
                throw new HttpException(409, 'Tên mục danh mục đã tồn tại.');
            }

            return $modelClass::create([
                'name'      => $name,
                'is_active' => true,
            ]);
        });
    }

    /**
     * CAT-002 (API-CAT-09): Đổi tên hoặc ẩn/kích hoạt mục danh mục (không hard delete).
     */
    public function lookupUpdate(string $catalogSlug, int $id, array $dto): Model
    {
        $modelClass = $this->lookupModel($catalogSlug);

        return DB::transaction(function () use ($modelClass, $id, $dto) {
            $item = $modelClass::where('id', $id)->lockForUpdate()->first();
            if (!$item) {
                throw new ModelNotFoundException('Không tìm thấy mục danh mục.');
            }

            if (array_key_exists('name', $dto)) {
                $name = (string) $dto['name'];

                $duplicate = $modelClass::query()
                    ->where('id', '!=', $item->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])
                    ->exists();

                if ($duplicate) {
                    throw new HttpException(409, 'Tên mục danh mục đã tồn tại.');
                }

                $item->name = $name;
            }

            if (array_key_exists('is_active', $dto)) {
                $item->is_active = (bool) $dto['is_active'];
            }

            $item->save();

            return $item->fresh();
        });
    }
}
