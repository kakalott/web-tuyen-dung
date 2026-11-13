<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendationRequest;
use App\Http\Resources\MatchResultResource;
use App\Services\MatchingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class RecommendationController extends Controller
{
    public function __construct(private MatchingService $matchingService)
    {
    }

    /**
     * API-AI-01: GET /api/v1/candidate/recommendations
     */
    public function recommend(RecommendationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->matchingService->recommend(
                $request->user(),
                (int) $validated['cv_id'],
                $validated
            );
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'CV_NOT_CONFIRMED') {
                return response()->json([
                    'error' => [
                        'code'    => 'CV_NOT_CONFIRMED',
                        'message' => 'Vui lòng xác nhận thông tin trích xuất CV trước khi nhận gợi ý việc làm.',
                        'fields'  => (object) [],
                    ],
                ], 409);
            }

            if ($e->getMessage() === 'AI_SERVICE_ERROR') {
                return response()->json([
                    'error' => [
                        'code'    => 'AI_SERVICE_ERROR',
                        'message' => 'Dịch vụ AI Matching tạm thời gián đoạn. Vui lòng thử lại sau.',
                        'fields'  => (object) [],
                    ],
                ], 502);
            }

            throw $e;
        }

        $items = collect($result['items'])->map(function (array $entry) use ($request) {
            $job = $entry['job'];
            $match = $entry['match_result'];
            $match->setAttribute('score_stale', (bool) ($entry['score_stale'] ?? false));

            $matchPayload = (new MatchResultResource($match))->resolve($request);

            return array_merge([
                'job_id'       => $job->id,
                'title'        => $job->title,
                'company_name' => $job->company?->company_name,
                'expires_at'   => $job->expires_at?->toIso8601String(),
            ], $matchPayload);
        })->values();

        return response()->json([
            'data' => [
                'items'         => $items,
                'coverage_note' => $result['coverage_note'],
                'pagination'    => $result['pagination'],
            ],
        ]);
    }
}
