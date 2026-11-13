<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmCVExtractionRequest;
use App\Models\CandidateCV;
use App\Services\CVService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CVExtractionController extends Controller
{
    public function __construct(private CVService $cvService) {}

    public function show(Request $request, int $cvId)
    {
        $cv = CandidateCV::findOrFail($cvId);
        Gate::authorize('view', $cv);

        $extraction = $cv->latestExtraction;
        if (!$extraction) {
            return response()->json([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'Chưa có kết quả trích xuất cho CV này.',
                    'fields' => (object)[]
                ]
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $extraction->id,
                'cv_id' => $extraction->cv_id,
                'extraction_status' => $extraction->extraction_status,
                'review_status' => $extraction->review_status,
                'extracted_text' => $extraction->extracted_text,
                'normalized_text' => $extraction->normalized_text,
                'confirmed_text' => $extraction->confirmed_text,
                'warnings' => $extraction->warnings,
                'skills' => $extraction->skills->map(fn($s) => [
                    'skill_id' => $s->id,
                    'name' => $s->name,
                    'source' => $s->pivot->source,
                    'review_status' => $s->pivot->review_status,
                ]),
            ]
        ]);
    }

    public function confirm(ConfirmCVExtractionRequest $request, int $cvId)
    {
        $cv = CandidateCV::findOrFail($cvId);
        Gate::authorize('update', $cv);
        
        $extraction = $this->cvService->confirm($request->user(), $cvId, $request->validated());
        
        return response()->json([
            'data' => [
                'extraction_id' => $extraction->id,
                'review_status' => $extraction->review_status,
                'confirmed_skill_ids' => $request->input('confirmed_skill_ids')
            ]
        ]);
    }

    public function retry(Request $request, int $cvId)
    {
        $cv = CandidateCV::findOrFail($cvId);
        Gate::authorize('update', $cv);

        // Có thể thực hiện đọc lại file từ storage_key và gọi lại extract
        return response()->json([
            'data' => [
                'retried' => true,
                'parse_status' => $cv->parse_status,
            ]
        ]);
    }
}
