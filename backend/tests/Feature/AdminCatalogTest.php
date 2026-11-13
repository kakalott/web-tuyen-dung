<?php

namespace Tests\Feature;

use App\Models\JobGroup;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role'           => 'admin',
            'account_status' => 'active',
        ]);
    }

    /**
     * TC-CAT-01: Chống xung đột tên chuẩn & alias trên phạm vi toàn cục.
     */
    public function test_skill_and_alias_global_collision_check(): void
    {
        $admin = $this->createAdmin();

        // 1. Tạo skill chuẩn "JavaScript"
        $resSkill = $this->actingAs($admin)->postJson('/api/v1/admin/catalog/skills', [
            'name' => 'JavaScript',
        ]);
        $resSkill->assertStatus(201)
            ->assertJsonPath('data.name', 'JavaScript')
            ->assertJsonPath('data.normalized_name', 'javascript');

        $jsSkillId = $resSkill->json('data.id');

        // 2. Tạo alias "JS" cho JavaScript
        $resAlias = $this->actingAs($admin)->postJson("/api/v1/admin/catalog/skills/{$jsSkillId}/aliases", [
            'alias' => 'JS',
        ]);
        $resAlias->assertStatus(201)
            ->assertJsonPath('data.normalized_alias', 'js');

        // 3. Thử tạo skill mới trùng với alias "js" (khác hoa thường & khoảng trắng) -> bị chặn 409
        $this->actingAs($admin)
            ->postJson('/api/v1/admin/catalog/skills', ['name' => '  js  '])
            ->assertStatus(409);

        // 4. Tạo skill khác "TypeScript" và thử gán alias trùng với skill chuẩn "javascript" -> bị chặn 409
        $tsSkillId = $this->actingAs($admin)
            ->postJson('/api/v1/admin/catalog/skills', ['name' => 'TypeScript'])
            ->assertStatus(201)
            ->json('data.id');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/catalog/skills/{$tsSkillId}/aliases", ['alias' => 'JAVASCRIPT'])
            ->assertStatus(409);
    }

    /**
     * TC-CAT-02: Ẩn kỹ năng (is_active = false) giữ nguyên ID, ẩn khỏi Public nhưng vẫn hiện ở Admin.
     */
    public function test_deactivate_skill_hides_from_public_but_keeps_id(): void
    {
        $admin = $this->createAdmin();

        $skill = Skill::create([
            'name'            => 'Docker',
            'normalized_name' => 'docker',
            'is_active'       => true,
        ]);

        // Đổi tên và ẩn kỹ năng qua API-CAT-03
        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/catalog/skills/{$skill->id}", [
                'name'      => 'Docker Engine',
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', 'Docker Engine')
            ->assertJsonPath('data.is_active', false);

        // API Public (API-SKL-01) không trả về kỹ năng đã ẩn
        $publicResponse = $this->getJson('/api/v1/catalog/skills');
        $publicResponse->assertStatus(200);
        $publicIds = collect($publicResponse->json('data.items') ?? $publicResponse->json('data'))
            ->pluck('id')
            ->all();
        $this->assertNotContains($skill->id, $publicIds);

        // API Admin (API-CAT-01) vẫn liệt kê đầy đủ kèm trạng thái is_active = false
        $adminResponse = $this->actingAs($admin)->getJson('/api/v1/admin/catalog/skills');
        $adminResponse->assertStatus(200)
            ->assertJsonFragment([
                'id'        => $skill->id,
                'name'      => 'Docker Engine',
                'is_active' => false,
            ]);
    }

    /**
     * TC-CAT-03 & TC-CAT-04: CRUD trên đủ 4 whitelist lookup slugs và từ chối slug lạ (SQLi guard).
     */
    public function test_four_lookup_slugs_crud_and_invalid_slug_rejection(): void
    {
        $admin = $this->createAdmin();
        $slugs = ['job-groups', 'employment-types', 'work-modes', 'seniority-levels'];

        foreach ($slugs as $slug) {
            // Create (API-CAT-08)
            $createRes = $this->actingAs($admin)->postJson("/api/v1/admin/catalog/{$slug}", [
                'name' => "Test {$slug}",
            ]);
            $createRes->assertStatus(201)
                ->assertJsonPath('data.is_active', true);

            $itemId = $createRes->json('data.id');

            // Update & Soft-disable (API-CAT-09)
            $this->actingAs($admin)
                ->patchJson("/api/v1/admin/catalog/{$slug}/{$itemId}", [
                    'name'      => "Updated {$slug}",
                    'is_active' => false,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.name', "Updated {$slug}")
                ->assertJsonPath('data.is_active', false);

            // Admin Index (API-CAT-07) vẫn thấy bản ghi đã ẩn
            $this->actingAs($admin)
                ->getJson("/api/v1/admin/catalog/{$slug}")
                ->assertStatus(200)
                ->assertJsonFragment([
                    'id'        => $itemId,
                    'name'      => "Updated {$slug}",
                    'is_active' => false,
                ]);
        }

        // Public Lookups (API-CAT-06) không trả về mục is_active = false
        $publicLookups = $this->getJson('/api/v1/catalog/lookups')->assertStatus(200)->json('data');
        $this->assertEmpty($publicLookups['job_groups'] ?? []);

        // Thử truyền slug không nằm trong whitelist -> bị chặn 404
        $this->actingAs($admin)
            ->getJson('/api/v1/admin/catalog/users')
            ->assertStatus(404);
    }

    /**
     * Kiểm tra phân quyền: Unauthenticated (401) và Non-Admin (403).
     */
    public function test_non_admin_and_guest_cannot_access_admin_catalog(): void
    {
        $candidate = User::factory()->create(['role' => 'candidate', 'account_status' => 'active']);
        $company   = User::factory()->create(['role' => 'company', 'account_status' => 'active']);

        // Guest -> 401
        $this->getJson('/api/v1/admin/catalog/skills')->assertStatus(401);

        // Candidate & Company -> 403
        $this->actingAs($candidate)->getJson('/api/v1/admin/catalog/skills')->assertStatus(403);
        $this->actingAs($company)->postJson('/api/v1/admin/catalog/job-groups', ['name' => 'IT'])->assertStatus(403);
    }
}
