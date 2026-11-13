<?php

namespace Tests\Feature;

use App\Clients\PythonAIClient;
use App\Models\CandidateCV;
use App\Models\CandidateProfile;
use App\Models\CVExtraction;
use App\Models\Skill;
use App\Models\SkillAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class CVTest extends TestCase
{
    use RefreshDatabase;

    private function createCandidate(string $email = 'cand_a@invalid.test'): array
    {
        $user = new User();
        $user->name = 'Candidate Demo';
        $user->email = $email;
        $user->password = bcrypt('Password123!');
        $user->role = 'candidate';
        $user->account_status = 'active';
        $user->save();

        $profile = CandidateProfile::create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function createFakePdf(string $name = 'cv-example.pdf', string $content = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * TC-CV-01: Valid Text PDF Upload & Extract -> lưu private disk, ẩn storage_key, nhận diện đúng skill_id
     */
    public function test_valid_pdf_upload_and_extract_happy_path(): void
    {
        Storage::fake('private_cvs');
        [$user, $profile] = $this->createCandidate();

        $phpSkill = Skill::create(['name' => 'PHP', 'normalized_name' => 'php', 'is_active' => true]);
        $jsSkill  = Skill::create(['name' => 'JavaScript', 'normalized_name' => 'javascript', 'is_active' => true]);
        SkillAlias::create(['skill_id' => $jsSkill->id, 'alias' => 'JS', 'normalized_alias' => 'js', 'is_active' => true]);

        // Mock PythonAIClient trả về kết quả trích xuất thành công
        $this->mock(PythonAIClient::class, function (MockInterface $mock) use ($phpSkill, $jsSkill) {
            $mock->shouldReceive('extractCv')
                ->once()
                ->andReturn([
                    'parser_status'   => 'success',
                    'extracted_text'  => 'Kinh nghiệm lập trình PHP và JS.',
                    'normalized_text' => 'kinh nghiệm lập trình php và js.',
                    'detected_skills' => [
                        ['skill_id' => $phpSkill->id, 'matched_alias' => 'PHP'],
                        ['skill_id' => $jsSkill->id,  'matched_alias' => 'JS'],
                    ],
                    'warnings'        => [],
                    'parser_version'  => 'pypdf-4.0.0',
                ]);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/candidate/cvs', [
            'file' => $this->createFakePdf('my-cv.pdf'),
        ]);

        $response->assertStatus(201)
            ->assertJsonMissing(['storage_key']);

        $cv = CandidateCV::where('candidate_profile_id', $profile->id)->firstOrFail();
        $this->assertEquals('processed', $cv->parse_status);

        // Kiểm tra tệp đã được lưu vật lý vào private disk theo storage_key
        Storage::disk('private_cvs')->assertExists($cv->storage_key);

        // Kiểm tra DB lưu bản ghi extraction & skills ở trạng thái draft/detected
        $extraction = CVExtraction::where('cv_id', $cv->id)->firstOrFail();
        $this->assertEquals('success', $extraction->extraction_status);
        $this->assertEquals('draft', $extraction->review_status);

        $this->assertDatabaseHas('cv_extraction_skills', [
            'cv_extraction_id' => $extraction->id,
            'skill_id'         => $jsSkill->id,
            'source'           => 'detected',
            'review_status'    => 'detected',
        ]);
    }

    /**
     * TC-CV-02: Fake MIME / Non-PDF Rejection -> trả 422 VALIDATION_ERROR
     */
    public function test_fake_mime_or_non_pdf_is_rejected(): void
    {
        Storage::fake('private_cvs');
        [$user] = $this->createCandidate();
        Sanctum::actingAs($user);

        // File text thuần đổi tên thành .pdf (không có magic bytes %PDF- và MIME text/plain)
        $fakeFile = UploadedFile::fake()->createWithContent('malicious.pdf', 'This is plain text, not a PDF');

        $response = $this->postJson('/api/v1/candidate/cvs', [
            'file' => $fakeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields'], 'request_id']);

        $this->assertDatabaseCount('candidate_cvs', 0);
    }

    /**
     * TC-CV-03: Image-only PDF No Text Layer -> parse_status = 'failed', warning PDF_NO_TEXT
     */
    public function test_image_only_pdf_returns_no_text_warning(): void
    {
        Storage::fake('private_cvs');
        [$user, $profile] = $this->createCandidate();

        $this->mock(PythonAIClient::class, function (MockInterface $mock) {
            $mock->shouldReceive('extractCv')
                ->once()
                ->andReturn([
                    'parser_status'   => 'failed',
                    'extracted_text'  => null,
                    'normalized_text' => null,
                    'detected_skills' => [],
                    'warnings'        => ['PDF_NO_TEXT'],
                    'parser_version'  => 'pypdf-4.0.0',
                ]);
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/candidate/cvs', [
            'file' => $this->createFakePdf('scanned-cv.pdf'),
        ]);

        $response->assertStatus(201);

        $cv = CandidateCV::where('candidate_profile_id', $profile->id)->firstOrFail();
        $this->assertEquals('failed', $cv->parse_status);
    }

    /**
     * TC-CV-04: Confirm Extraction & Disjoint Skills Validation
     */
    public function test_confirm_extraction_and_disjoint_skills_validation(): void
    {
        [$user, $profile] = $this->createCandidate();
        $skillA = Skill::create(['name' => 'Laravel', 'normalized_name' => 'laravel', 'is_active' => true]);
        $skillB = Skill::create(['name' => 'MySQL', 'normalized_name' => 'mysql', 'is_active' => true]);

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = 'cv.pdf';
        $cv->storage_key          = 'uuid-test-key.pdf';
        $cv->mime_type            = 'application/pdf';
        $cv->file_size            = 1024;
        $cv->sha256               = str_repeat('a', 64);
        $cv->parse_status         = 'processed';
        $cv->uploaded_at          = now();
        $cv->save();

        $extraction = CVExtraction::create([
            'cv_id'             => $cv->id,
            'extraction_status' => 'success',
            'review_status'     => 'draft',
            'extracted_text'    => 'Raw text',
            'extracted_at'      => now(),
        ]);

        Sanctum::actingAs($user);

        // 1. Thử gửi trùng skill_id giữa confirmed_skill_ids và rejected_skill_ids -> 422
        $this->patchJson("/api/v1/candidate/cvs/{$cv->id}/extraction", [
            'confirmed_text'      => 'Edited CV text',
            'confirmed_skill_ids' => [$skillA->id],
            'rejected_skill_ids'  => [$skillA->id],
        ])->assertStatus(422)
          ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 2. Gửi dữ liệu hợp lệ -> 200 & chuyển review_status = 'confirmed'
        $resConfirm = $this->patchJson("/api/v1/candidate/cvs/{$cv->id}/extraction", [
            'confirmed_text'      => 'Kinh nghiệm Laravel và MySQL thực chiến.',
            'confirmed_skill_ids' => [$skillA->id],
            'rejected_skill_ids'  => [$skillB->id],
        ]);

        $resConfirm->assertStatus(200)
            ->assertJsonPath('data.review_status', 'confirmed');

        $this->assertDatabaseHas('cv_extractions', [
            'id'             => $extraction->id,
            'confirmed_text' => 'Kinh nghiệm Laravel và MySQL thực chiến.',
            'review_status'  => 'confirmed',
        ]);

        $this->assertDatabaseHas('cv_extraction_skills', [
            'cv_extraction_id' => $extraction->id,
            'skill_id'         => $skillA->id,
            'review_status'    => 'confirmed',
        ]);

        $this->assertDatabaseHas('cv_extraction_skills', [
            'cv_extraction_id' => $extraction->id,
            'skill_id'         => $skillB->id,
            'review_status'    => 'rejected',
        ]);
    }

    /**
     * TC-CV-05: Private CV Stream & Cross-Candidate IDOR Block + Ẩn storage_key
     */
    public function test_private_cv_stream_and_cross_candidate_idor_block(): void
    {
        Storage::fake('private_cvs');
        [$candA, $profileA] = $this->createCandidate('cand_a@invalid.test');
        [$candB]            = $this->createCandidate('cand_b@invalid.test');

        $storageKey = 'secret-uuid-cv-a.pdf';
        Storage::disk('private_cvs')->put($storageKey, '%PDF-1.4 private binary content');

        $cvA = new CandidateCV();
        $cvA->candidate_profile_id = $profileA->id;
        $cvA->original_filename    = 'cv-candidate-a.pdf';
        $cvA->storage_key          = $storageKey;
        $cvA->mime_type            = 'application/pdf';
        $cvA->file_size            = 512;
        $cvA->sha256               = str_repeat('b', 64);
        $cvA->parse_status         = 'processed';
        $cvA->uploaded_at          = now();
        $cvA->save();

        // 1. Candidate A liệt kê danh sách CV -> JSON tuyệt đối không lộ storage_key
        Sanctum::actingAs($candA);
        $listRes = $this->getJson('/api/v1/candidate/cvs');
        $listRes->assertStatus(200)
            ->assertJsonMissing(['storage_key']);
        $this->assertStringNotContainsString($storageKey, $listRes->getContent());

        // 2. Candidate A tải file của chính mình -> 200 application/pdf + no-store
        $downloadRes = $this->get("/api/v1/candidate/cvs/{$cvA->id}/file");
        $downloadRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', $downloadRes->headers->get('Cache-Control'));

        // 3. Candidate B thử tải CV hoặc xác nhận extraction của Candidate A -> bị chặn 403
        Sanctum::actingAs($candB);
        $this->getJson("/api/v1/candidate/cvs/{$cvA->id}/file")
            ->assertStatus(403);

        $this->patchJson("/api/v1/candidate/cvs/{$cvA->id}/extraction", [
            'confirmed_text'      => 'Hacked text',
            'confirmed_skill_ids' => [],
        ])->assertStatus(403);
    }
}
