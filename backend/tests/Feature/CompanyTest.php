<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private function createCompanyUser(string $email = 'hr@example.invalid.test'): array
    {
        $user = User::factory()->create([
            'name'           => 'HR Manager',
            'email'          => $email,
            'role'           => 'company',
            'account_status' => 'active',
        ]);

        $company = Company::create([
            'user_id'             => $user->id,
            'company_name'        => 'Example Tech Corp',
            'description'         => 'Initial description',
            'website_url'         => 'https://example.invalid.test',
            'verification_status' => 'unverified',
        ]);

        return [$user, $company];
    }

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'name'           => 'System Admin',
            'email'          => 'admin@example.invalid.test',
            'role'           => 'admin',
            'account_status' => 'active',
        ]);
    }

    /**
     * TC-COM-01: Doanh nghiệp xem và cập nhật thông tin hồ sơ công ty thành công.
     */
    public function test_company_can_view_and_update_own_profile(): void
    {
        [$companyUser, $company] = $this->createCompanyUser();
        Sanctum::actingAs($companyUser);

        // 1. Xem hồ sơ (API-COM-01)
        $getRes = $this->getJson('/api/v1/company/profile');
        $getRes->assertStatus(200)
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.company_name', 'Example Tech Corp')
            ->assertJsonPath('data.verification_status', 'unverified');

        // 2. Cập nhật hồ sơ (API-COM-02)
        $patchRes = $this->patchJson('/api/v1/company/profile', [
            'company_name' => 'Example AI Solutions JSC',
            'description'  => 'Updated company profile for verification',
            'website_url'  => 'https://ai.example.invalid.test',
        ]);

        $patchRes->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Example AI Solutions JSC')
            ->assertJsonPath('data.description', 'Updated company profile for verification')
            ->assertJsonPath('data.website_url', 'https://ai.example.invalid.test')
            ->assertJsonPath('data.verification_status', 'unverified');

        $this->assertDatabaseHas('companies', [
            'id'                  => $company->id,
            'company_name'        => 'Example AI Solutions JSC',
            'verification_status' => 'unverified',
        ]);
    }

    /**
     * TC-COM-02: Chặn doanh nghiệp tự gán verification_status = verified qua API cập nhật hồ sơ.
     */
    public function test_company_cannot_self_verify_via_profile_update(): void
    {
        [$companyUser, $company] = $this->createCompanyUser();
        Sanctum::actingAs($companyUser);

        $response = $this->patchJson('/api/v1/company/profile', [
            'company_name'        => 'Sneaky Verified Corp',
            'description'         => 'Trying to bypass admin verification',
            'website_url'         => 'https://sneaky.example.invalid.test',
            'verification_status' => 'verified',
            'verified_by'         => $companyUser->id,
            'verified_at'         => now()->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.company_name', 'Sneaky Verified Corp')
            ->assertJsonPath('data.verification_status', 'unverified')
            ->assertJsonPath('data.verified_by', null)
            ->assertJsonPath('data.verified_at', null);

        $this->assertDatabaseHas('companies', [
            'id'                  => $company->id,
            'verification_status' => 'unverified',
            'verified_by'         => null,
            'verified_at'         => null,
        ]);
    }

    /**
     * TC-COM-03: Luồng Admin xem danh sách chờ duyệt, xem chi tiết và xác minh / từ chối doanh nghiệp.
     */
    public function test_admin_can_list_inspect_and_verify_company(): void
    {
        [$companyUser, $company] = $this->createCompanyUser();
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        // 1. Lấy danh sách doanh nghiệp chưa xác minh (API-ADM-01)
        $listRes = $this->getJson('/api/v1/admin/companies/unverified');
        $listRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $company->id)
            ->assertJsonPath('data.0.user.email', $companyUser->email);

        // 2. Xem chi tiết doanh nghiệp (API-ADM-02)
        $detailRes = $this->getJson("/api/v1/admin/companies/{$company->id}");
        $detailRes->assertStatus(200)
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.verification_status', 'unverified');

        // 3. Admin duyệt xác minh thành công (API-ADM-03 -> verified)
        $approveRes = $this->patchJson("/api/v1/admin/companies/{$company->id}/verification", [
            'verification_status' => 'verified',
            'note'                => 'Đã đối chiếu thông tin website và hồ sơ doanh nghiệp hợp lệ.',
        ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.verified_by', $admin->id)
            ->assertJsonPath('data.verification_note', 'Đã đối chiếu thông tin website và hồ sơ doanh nghiệp hợp lệ.');

        $company->refresh();
        $this->assertEquals('verified', $company->verification_status);
        $this->assertEquals($admin->id, $company->verified_by);
        $this->assertNotNull($company->verified_at);

        // 4. Danh sách chờ xác minh lúc này phải rỗng
        $this->getJson('/api/v1/admin/companies/unverified')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        // 5. Từ chối / hủy xác minh bắt buộc phải có note (422)
        $this->patchJson("/api/v1/admin/companies/{$company->id}/verification", [
            'verification_status' => 'unverified',
            'note'                => '',
        ])->assertStatus(422);

        // 6. Hủy xác minh kèm lý do hợp lệ -> verified_at quay về null
        $revokeRes = $this->patchJson("/api/v1/admin/companies/{$company->id}/verification", [
            'verification_status' => 'unverified',
            'note'                => 'Website doanh nghiệp không phản hồi, yêu cầu cập nhật lại.',
        ]);

        $revokeRes->assertStatus(200)
            ->assertJsonPath('data.verification_status', 'unverified')
            ->assertJsonPath('data.verified_by', $admin->id)
            ->assertJsonPath('data.verified_at', null);
    }

    /**
     * TC-COM-04: Chặn truy cập sai vai trò (RBAC) vào API của Company và Admin.
     */
    public function test_role_guards_block_unauthorized_access(): void
    {
        [$companyUser, $company] = $this->createCompanyUser();
        $candidate = User::factory()->create([
            'role'           => 'candidate',
            'account_status' => 'active',
        ]);

        // Candidate gọi API Company -> 403
        Sanctum::actingAs($candidate);
        $this->getJson('/api/v1/company/profile')->assertStatus(403);
        $this->getJson('/api/v1/admin/companies/unverified')->assertStatus(403);

        // Company gọi API Admin xác minh chính mình -> 403
        Sanctum::actingAs($companyUser);
        $this->getJson('/api/v1/admin/companies/unverified')->assertStatus(403);
        $this->patchJson("/api/v1/admin/companies/{$company->id}/verification", [
            'verification_status' => 'verified',
        ])->assertStatus(403);
    }
}
