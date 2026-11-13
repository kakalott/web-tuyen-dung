<?php

namespace App\Services;

use App\Models\User;
use App\Models\CandidateCV;
use App\Models\CVExtraction;
use App\Models\Skill;
use App\Clients\PythonAIClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $cv->update(['parse_status' => 'failed']);
        }
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
}
