<?php

namespace App\Services;

use App\Models\User;
use App\Models\CandidateCV;
use App\Models\CandidateProfile;
use App\Models\CVExtraction;
use App\Models\Skill;
use App\Models\Application;
use App\Models\Company;
use App\Clients\PythonAIClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CVService
{
    public function __construct(private PythonAIClient $aiClient) {}

    public function upload(User $actor, UploadedFile $pdf): CandidateCV
    {
        $profile = $actor->candidateProfile;
        
        // Tạo khóa ngẫu nhiên, không dùng original_filename
        $storageKey = (string) Str::uuid() . '.pdf';
        $pdf->storeAs('', $storageKey, 'private_cvs');
        $sha256 = hash_file('sha256', $pdf->getRealPath());

        $cv = new CandidateCV();
        $cv->candidate_profile_id = $profile->id;
        $cv->original_filename    = $pdf->getClientOriginalName();
        $cv->storage_key          = $storageKey;
        $cv->mime_type            = $pdf->getMimeType() ?: 'application/pdf';
        $cv->file_size            = $pdf->getSize();
        $cv->sha256               = $sha256;
        $cv->parse_status         = 'processing';
        $cv->uploaded_at          = now();
        $cv->save();

        return $cv;
    }

    public function extract(CandidateCV $cv, UploadedFile $pdf): void
    {
        try {
            // Lấy danh mục kỹ năng đang active kèm alias
            $catalog = Skill::with('aliases')->where('is_active', 1)->get()->map(fn($s) => [
                'skill_id'       => $s->id,
                'canonical_name' => $s->name,
                'aliases'        => $s->aliases->pluck('alias')->toArray()
            ])->toArray();

            // Gọi Python API bên ngoài DB transaction
            $result = $this->aiClient->extractCv($pdf, $catalog);

            DB::transaction(function () use ($cv, $result) {
                if ($result['parser_status'] === 'success') {
                    $cv->update(['parse_status' => 'processed']);
                    
                    $extraction = CVExtraction::create([
                        'cv_id'             => $cv->id,
                        'extraction_status' => 'success',
                        'review_status'     => 'draft',
                        'extracted_text'    => $result['extracted_text'] ?? null,
                        'normalized_text'   => $result['normalized_text'] ?? null,
                        'warnings'          => $result['warnings'] ?? [],
                        'extracted_at'      => now(),
                    ]);

                    $skillsToAttach = [];
                    foreach ($result['detected_skills'] ?? [] as $ds) {
                        $skillsToAttach[$ds['skill_id']] = [
                            'source'        => 'detected',
                            'review_status' => 'detected',
                        ];
                    }
                    if (!empty($skillsToAttach)) {
                        $extraction->skills()->attach($skillsToAttach);
                    }
                } else {
                    $cv->update(['parse_status' => 'failed']);
                    CVExtraction::create([
                        'cv_id'             => $cv->id,
                        'extraction_status' => 'failed',
                        'review_status'     => 'draft',
                        'warnings'          => $result['warnings'] ?? [],
                        'extracted_at'      => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('CV_EXTRACTION_FAILED', [
                'cv_id' => $cv->id,
                'error' => $e->getMessage()
            ]);
            $cv->update(['parse_status' => 'failed']);
        }
    }

    public function retryExtraction(User $actor, int $cvId): CandidateCV
    {
        $cv = CandidateCV::where('id', $cvId)->firstOrFail();
        $disk = Storage::disk('private_cvs');
        if (!$disk->exists($cv->storage_key)) {
            throw new HttpException(404, 'Không tìm thấy file CV trong hệ thống lưu trữ.');
        }

        $fullPath = $disk->path($cv->storage_key);

        $catalog = Skill::with('aliases')->where('is_active', 1)->get()->map(fn($s) => [
            'skill_id'       => $s->id,
            'canonical_name' => $s->name,
            'aliases'        => $s->aliases->pluck('alias')->toArray()
        ])->toArray();

        try {
            $result = $this->aiClient->extractCv($fullPath, $catalog, $cv->original_filename);

            DB::transaction(function () use ($cv, $result) {
                if ($result['parser_status'] === 'success') {
                    $cv->update(['parse_status' => 'processed']);
                    
                    $extraction = CVExtraction::create([
                        'cv_id'             => $cv->id,
                        'extraction_status' => 'success',
                        'review_status'     => 'draft',
                        'extracted_text'    => $result['extracted_text'] ?? null,
                        'normalized_text'   => $result['normalized_text'] ?? null,
                        'warnings'          => $result['warnings'] ?? [],
                        'extracted_at'      => now(),
                    ]);

                    $skillsToAttach = [];
                    foreach ($result['detected_skills'] ?? [] as $ds) {
                        $skillsToAttach[$ds['skill_id']] = [
                            'source'        => 'detected',
                            'review_status' => 'detected',
                        ];
                    }
                    if (!empty($skillsToAttach)) {
                        $extraction->skills()->attach($skillsToAttach);
                    }
                } else {
                    $cv->update(['parse_status' => 'failed']);
                    CVExtraction::create([
                        'cv_id'             => $cv->id,
                        'extraction_status' => 'failed',
                        'review_status'     => 'draft',
                        'warnings'          => $result['warnings'] ?? [],
                        'extracted_at'      => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('CV_RETRY_EXTRACTION_FAILED', [
                'cv_id' => $cv->id,
                'error' => $e->getMessage()
            ]);
            $cv->update(['parse_status' => 'failed']);
            throw $e;
        }

        return $cv->fresh();
    }

    public function confirm(User $actor, int $cvId, array $dto): CVExtraction
    {
        return DB::transaction(function () use ($cvId, $dto) {
            $extraction = CVExtraction::where('cv_id', $cvId)
                ->where('extraction_status', 'success')
                ->latest()->firstOrFail();

            $extraction->update([
                'confirmed_text' => $dto['confirmed_text'],
                'review_status'  => 'confirmed',
                'confirmed_at'   => now(),
            ]);

            $syncData = [];
            foreach ($dto['confirmed_skill_ids'] as $skillId) {
                $syncData[$skillId] = ['source' => 'manual', 'review_status' => 'confirmed'];
            }
            foreach ($dto['rejected_skill_ids'] ?? [] as $skillId) {
                $syncData[$skillId] = ['source' => 'manual', 'review_status' => 'rejected'];
            }

            $extraction->skills()->sync($syncData);

            // Đồng bộ tự động các kỹ năng đã xác nhận sang candidate_skills của ứng viên (source = 'cv_confirmed')
            $cv = CandidateCV::find($cvId);
            if ($cv && ($profile = CandidateProfile::find($cv->candidate_profile_id))) {
                $existing = $profile->skills()->pluck('skills.id')->all();
                $toAttach = array_diff($dto['confirmed_skill_ids'], $existing);
                if (!empty($toAttach)) {
                    $attachData = [];
                    foreach ($toAttach as $sid) {
                        $attachData[$sid] = ['source' => 'cv_confirmed'];
                    }
                    $profile->skills()->attach($attachData);
                }
            }

            return $extraction->load('skills');
        });
    }

    public function downloadOwn(User $actor, int $cvId)
    {
        $cv = CandidateCV::where('id', $cvId)->firstOrFail();
        return Storage::disk('private_cvs')->download($cv->storage_key, $cv->original_filename, [
            'Content-Type'        => 'application/pdf',
            'Cache-Control'       => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="' . $cv->original_filename . '"'
        ]);
    }

    /**
     * CV-004 (API-APP-07): Doanh nghiệp đã xác minh xem/tải CV ứng viên qua luồng riêng tư.
     * Thực thi nghiêm ngặt thuật toán kiểm tra quyền 5 bước (Mục 11.3 Blueprint & BR-SEC-02).
     */
    public function downloadForApplication(User $actor, int $applicationId): StreamedResponse
    {
        // Bước 1: Kiểm tra vai trò company và trạng thái xác minh verified
        if ($actor->role !== 'company') {
            throw new HttpException(403, 'Chỉ tài khoản doanh nghiệp mới được phép truy cập endpoint này.');
        }

        $company = Company::where('user_id', $actor->id)->first();
        if (!$company || $company->verification_status !== 'verified') {
            throw new HttpException(403, 'Doanh nghiệp chưa được xác minh nên không được phép xem/tải CV ứng viên.');
        }

        // Bước 2: Tìm đơn ứng tuyển và kiểm tra chuỗi quyền company -> job -> application
        $application = Application::with(['job', 'cv'])->find($applicationId);
        if (!$application) {
            throw new ModelNotFoundException('Không tìm thấy đơn ứng tuyển.');
        }

        if (!$application->job || $application->job->company_id !== $company->id) {
            throw new HttpException(403, 'Bạn không có quyền truy cập CV của đơn ứng tuyển thuộc doanh nghiệp khác.');
        }

        // Bước 3: Xác thực CV Snapshot cố định tại thời điểm nộp đơn (D-02)
        $cv = $application->cv;
        if (
            !$cv
            || $cv->id !== $application->cv_id
            || $cv->candidate_profile_id !== $application->candidate_profile_id
        ) {
            throw new ModelNotFoundException('Không tìm thấy tệp CV gắn với đơn ứng tuyển này.');
        }

        // Bước 4: Kiểm tra sự tồn tại vật lý trên private disk
        $disk = Storage::disk('private_cvs');
        if (!$disk->exists($cv->storage_key)) {
            throw new HttpException(404, 'Tệp CV vật lý không còn tồn tại trên hệ thống lưu trữ.');
        }

        // Bước 5: Ghi log truy cập tối thiểu (NFR-LOG / Mục 11.2)
        Log::info('company_cv_access', [
            'user_id'        => $actor->id,
            'company_id'     => $company->id,
            'application_id' => $application->id,
            'cv_id'          => $cv->id,
            'accessed_at'    => now()->toIso8601String(),
        ]);

        $safeFilename = str_replace('"', '', $cv->original_filename ?: 'candidate-cv.pdf');

        return $disk->response(
            $cv->storage_key,
            $safeFilename,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
                'Cache-Control'       => 'private, no-store, max-age=0',
            ]
        );
    }
}
