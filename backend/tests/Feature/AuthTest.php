<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CandidateProfile;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_happy_candidate_and_company(): void
    {
        // TC-AUTH-01: Đăng ký ứng viên
        $candidateRes = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Candidate User',
            'email'    => 'candidate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role'     => 'candidate',
        ]);

        $candidateRes->assertStatus(201)
            ->assertJsonPath('data.role', 'candidate')
            ->assertJsonMissing(['password']);
                 
        $this->assertDatabaseHas('candidate_profiles', ['user_id' => $candidateRes->json('data.id')]);

        // TC-AUTH-01: Đăng ký doanh nghiệp
        $companyRes = $this->postJson('/api/v1/auth/register', [
            'name'         => 'Tech Corp',
            'email'        => 'company@example.com',
            'password'     => 'password123',
            'password_confirmation' => 'password123',
            'role'         => 'company',
            'company_name' => 'Tech Corp',
        ]);

        $companyRes->assertStatus(201)->assertJsonPath('data.role', 'company');
        $this->assertDatabaseHas('companies', [
            'user_id' => $companyRes->json('data.id'),
            'verification_status' => 'unverified'
        ]);
    }

    public function test_block_admin_registration_and_duplicate_email(): void
    {
        // TC-AUTH-02: Cấm role = admin
        $this->postJson('/api/v1/auth/register', [
            'name'     => 'Hacker',
            'email'    => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role'     => 'admin',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);

        // TC-AUTH-02: Cấm trùng email
        User::factory()->create(['email' => 'duplicate@example.com']);
        $this->postJson('/api/v1/auth/register', [
            'name'     => 'User 2',
            'email'    => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role'     => 'candidate',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Nguyen Van B',
            'email'                 => 'candidate2@example.com',
            'password'              => 'Password123',
            'password_confirmation' => 'DifferentPassword123',
            'role'                  => 'candidate',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['fields' => ['password']]]);
    }

    public function test_login_me_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'candidate',
            'account_status' => 'active'
        ]);
        CandidateProfile::create(['user_id' => $user->id]);

        // TC-AUTH-03: Login thành công
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonPath('data.authenticated', true);

        // Sinh token ảo để test route được bảo vệ
        $token = $user->createToken('test')->plainTextToken;

        // TC-AUTH-03: Lấy ME profile
        $meRes = $this->withHeaders(['Authorization' => "Bearer $token"])
             ->getJson('/api/v1/me');
        
        $meRes->assertStatus(200)
             ->assertJsonPath('data.role', 'candidate');
        $this->assertNotNull($meRes->json('data.profile_id'));

        // TC-AUTH-03: Logout
        $this->withHeaders(['Authorization' => "Bearer $token"])
             ->postJson('/api/v1/auth/logout')
             ->assertStatus(200);
    }

    public function test_wrong_credentials_locked_account(): void
    {
        $user = User::factory()->create([
            'email' => 'lockme@example.com',
            'password' => bcrypt('password123'),
            'account_status' => 'locked'
        ]);

        // TC-AUTH-04: Sai mật khẩu không lộ trạng thái email
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpass',
        ])->assertStatus(401);

        // TC-AUTH-04: Đúng thông tin nhưng tài khoản bị khóa
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertStatus(403);
        
        // TC-AUTH-04: Gọi ME khi bị khóa
        $token = $user->createToken('test')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"])
             ->getJson('/api/v1/me')
             ->assertStatus(403);
    }
}
