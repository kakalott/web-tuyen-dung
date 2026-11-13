<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CandidateTest extends TestCase
{
    use RefreshDatabase;

    private function createCandidateUser(string $email = 'cand_a@example.com'): array
    {
        $user = new User();
        $user->name = 'Nguyen Van A';
        $user->email = $email;
        $user->password = bcrypt('Password123!');
        $user->role = 'candidate';
        $user->account_status = 'active';
        $user->save();

        $profile = CandidateProfile::create([
            'user_id'  => $user->id,
            'summary'  => 'Initial summary',
            'phone'    => '0901234567',
            'location' => 'Ha Noi',
        ]);

        return [$user, $profile];
    }

    /**
     * TC-CAN-01: Profile & Nested CRUD (Education & Experience) Happy Path
     */
    public function test_candidate_profile_and_nested_crud_happy_path(): void
    {
        [$user, $profile] = $this->createCandidateUser();
        Sanctum::actingAs($user);

        // 1. Update Profile (API-CAN-02)
        $resUpdate = $this->patchJson('/api/v1/candidate/profile', [
            'summary'  => 'Backend Laravel Developer',
            'phone'    => '0988888888',
            'location' => 'Ho Chi Minh',
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.summary', 'Backend Laravel Developer')
            ->assertJsonPath('data.location', 'Ho Chi Minh');

        // 2. Store Education (API-CAN-03)
        $resEduCreate = $this->postJson('/api/v1/candidate/educations', [
            'institution'    => 'HUST',
            'degree'         => 'Bachelor',
            'field_of_study' => 'Computer Science',
            'start_date'     => '2020-09-01',
            'end_date'       => '2024-06-30',
            'description'    => 'Graduated with honors',
        ]);
        $resEduCreate->assertStatus(201);
        $eduId = $resEduCreate->json('data.id');

        // 3. Update Education (API-CAN-04)
        $resEduUpdate = $this->patchJson("/api/v1/candidate/educations/{$eduId}", [
            'institution'    => 'HUST',
            'degree'         => 'Engineer',
            'field_of_study' => 'Software Engineering',
            'start_date'     => '2020-09-01',
            'end_date'       => '2024-06-30',
        ]);
        $resEduUpdate->assertStatus(200)
            ->assertJsonPath('data.degree', 'Engineer');

        // 4. Store & Update Experience (API-CAN-06, API-CAN-07)
        $resExpCreate = $this->postJson('/api/v1/candidate/experiences', [
            'employer_name' => 'Tech Corp',
            'job_title'     => 'PHP Developer',
            'start_date'    => '2024-07-01',
            'end_date'      => null,
            'description'   => 'Build RESTful APIs',
        ]);
        $resExpCreate->assertStatus(201);
        $expId = $resExpCreate->json('data.id');

        $resExpUpdate = $this->patchJson("/api/v1/candidate/experiences/{$expId}", [
            'employer_name' => 'Tech Corp Vietnam',
            'job_title'     => 'Senior PHP Developer',
            'start_date'    => '2024-07-01',
            'end_date'      => '2025-12-31',
        ]);
        $resExpUpdate->assertStatus(200)
            ->assertJsonPath('data.job_title', 'Senior PHP Developer');

        // 5. Show Profile with nested lists (API-CAN-01)
        $resShow = $this->getJson('/api/v1/candidate/profile');
        $resShow->assertStatus(200)
            ->assertJsonCount(1, 'data.educations')
            ->assertJsonCount(1, 'data.experiences');

        // 6. Delete Education & Experience (API-CAN-05, API-CAN-08)
        $this->deleteJson("/api/v1/candidate/educations/{$eduId}")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson("/api/v1/candidate/experiences/{$expId}")
            ->assertStatus(200)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('educations', ['id' => $eduId]);
        $this->assertDatabaseMissing('experiences', ['id' => $expId]);
    }

    /**
     * TC-CAN-02: Invalid Date Range returns 422 VALIDATION_ERROR
     */
    public function test_invalid_date_range_returns_422(): void
    {
        [$user] = $this->createCandidateUser();
        Sanctum::actingAs($user);

        $resEdu = $this->postJson('/api/v1/candidate/educations', [
            'institution' => 'VNU',
            'degree'      => 'Bachelor',
            'start_date'  => '2026-05-01',
            'end_date'    => '2025-01-01',
        ]);

        $resEdu->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields'], 'request_id']);

        $resExp = $this->postJson('/api/v1/candidate/experiences', [
            'employer_name' => 'Acme',
            'job_title'     => 'Dev',
            'start_date'    => '2026-05-01',
            'end_date'      => '2025-01-01',
        ]);

        $resExp->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * TC-CAN-03: Cross-Owner IDOR Protection
     */
    public function test_cross_owner_idor_is_blocked(): void
    {
        [$candA] = $this->createCandidateUser('cand_a@example.com');
        [, $profileB] = $this->createCandidateUser('cand_b@example.com');

        $eduB = Education::create([
            'candidate_profile_id' => $profileB->id,
            'institution'          => 'PTIT',
            'degree'               => 'Bachelor',
            'start_date'           => '2020-01-01',
        ]);

        $expB = Experience::create([
            'candidate_profile_id' => $profileB->id,
            'employer_name'        => 'Company B',
            'job_title'            => 'Engineer',
            'start_date'           => '2023-01-01',
        ]);

        Sanctum::actingAs($candA);

        // Candidate A tries to PATCH/DELETE Candidate B's education
        $this->patchJson("/api/v1/candidate/educations/{$eduB->id}", [
            'institution' => 'Hacked',
            'degree'      => 'Hacked',
            'start_date'  => '2020-01-01',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/candidate/educations/{$eduB->id}")
            ->assertStatus(403);

        // Candidate A tries to PATCH/DELETE Candidate B's experience
        $this->patchJson("/api/v1/candidate/experiences/{$expB->id}", [
            'employer_name' => 'Hacked',
            'job_title'     => 'Hacked',
            'start_date'    => '2023-01-01',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/candidate/experiences/{$expB->id}")
            ->assertStatus(403);

        // Verify Candidate B's records remain untouched
        $this->assertDatabaseHas('educations', ['id' => $eduB->id, 'institution' => 'PTIT']);
        $this->assertDatabaseHas('experiences', ['id' => $expB->id, 'employer_name' => 'Company B']);
    }

    /**
     * TC-CAN-04: Skill Search by Alias & Sync Active Skills Only
     */
    public function test_skill_search_by_alias_and_sync_active_skills(): void
    {
        [$user, $profile] = $this->createCandidateUser();

        $jsSkill = Skill::create([
            'name'            => 'JavaScript',
            'normalized_name' => 'javascript',
            'is_active'       => true,
        ]);

        SkillAlias::create([
            'skill_id'         => $jsSkill->id,
            'alias'            => 'JS',
            'normalized_alias' => 'js',
            'is_active'        => true,
        ]);

        SkillAlias::create([
            'skill_id'         => $jsSkill->id,
            'alias'            => 'JS ES6',
            'normalized_alias' => 'js es6',
            'is_active'        => true,
        ]);

        $inactiveSkill = Skill::create([
            'name'            => 'Legacy Flash',
            'normalized_name' => 'legacy flash',
            'is_active'       => false,
        ]);

        // 1. Public search by alias "JS" returns canonical "JavaScript" without duplicates (API-SKL-01)
        $resSearch = $this->getJson('/api/v1/catalog/skills?search=JS');
        $resSearch->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $jsSkill->id)
            ->assertJsonPath('data.0.name', 'JavaScript');

        // 2. Sync active skill succeeds (API-SKL-03)
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/candidate/skills', [
            'skill_ids' => [$jsSkill->id],
        ])->assertStatus(200)
            ->assertJsonPath('data.skill_ids', [$jsSkill->id]);

        $this->assertDatabaseHas('candidate_skills', [
            'candidate_profile_id' => $profile->id,
            'skill_id'             => $jsSkill->id,
            'source'               => 'manual',
        ]);

        // 3. List candidate skills (API-SKL-02)
        $this->getJson('/api/v1/candidate/skills')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'JavaScript');

        // 4. Attempt to sync inactive skill returns 422
        $this->putJson('/api/v1/candidate/skills', [
            'skill_ids' => [$inactiveSkill->id],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * TC-CAN-05: Role Guard (Company/Admin blocked with 403) & Unauthenticated (401)
     */
    public function test_non_candidate_roles_are_forbidden(): void
    {
        // Unauthenticated -> 401
        $this->getJson('/api/v1/candidate/profile')
            ->assertStatus(401);

        // Company user -> 403
        $companyUser = new User();
        $companyUser->name = 'HR Manager';
        $companyUser->email = 'hr@company.com';
        $companyUser->password = bcrypt('Password123!');
        $companyUser->role = 'company';
        $companyUser->account_status = 'active';
        $companyUser->save();

        Company::create([
            'user_id'             => $companyUser->id,
            'company_name'        => 'Acme Corp',
            'verification_status' => 'unverified',
        ]);

        Sanctum::actingAs($companyUser);

        $this->getJson('/api/v1/candidate/profile')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->putJson('/api/v1/candidate/skills', ['skill_ids' => []])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
