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
     * API-AI-01: Gợi ý việc làm theo CV đã confirmed.
     * GET /api/v1/candidate/recommendations?cv_id=...
     */
    public function recommend(RecommendationRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cvId = (int) $request->input('cv_id');
            $filters = [
                'page'      => $request->input('page', 1),
                'page_size' => $request->input('page_size', 10),
            ];

            $result = $this->matchingService->recommend($user, $cvId, $filters);

            $formattedItems = array_map(function ($item) use ($request) {
                $job = $item['job'];
                $matchResult = $item['match_result'];

                return [
                    'job' => [
                        'id'                 => $job->id,
                        'title'              => $job->title,
                        'company_id'         => $job->company_id,
                        'company_name'       => $job->company?->company_name,
                        'location'           => $job->location,
                        'salary_min'         => $job->salary_min,
                        'salary_max'         => $job->salary_max,
                        'currency'           => $job->currency,
                        'publication_status' => $job->publication_status,
                        'published_at'       => $job->published_at?->toIso8601String(),
                        'expires_at'         => $job->expires_at?->toIso8601String(),
                    ],
                    'match_result' => (new MatchResultResource($matchResult))->toArray($request),
                    'score_stale'  => (bool) $item['score_stale'],
                ];
            }, $result['items']);

            return response()->json([
                'data' => [
                    'items'         => $formattedItems,
                    'coverage_note' => $result['coverage_note'],
                    'pagination'    => $result['pagination'],
                ],
                'request_id' => $request->header('X-Request-Id', 'req_' . bin2hex(random_bytes(8))),
            ], 200);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

            if ($code === 'CV_NOT_CONFIRMED') {
                $status = 409;
            } elseif ($code === 'AI_SERVICE_ERROR') {
                $status = 502;
            }

            return response()->json([
                'error' => [
                    'code'    => $code,
                    'message' => $this->resolveErrorMessage($code),
                ],
                'request_id' => $request->header('X-Request-Id', 'req_' . bin2hex(random_bytes(8))),
            ], $status);
        }
    }

    private function resolveErrorMessage(string $code): string
    {
        return match ($code) {
            'CV_NOT_CONFIRMED' => 'CV chưa được xác nhận trích xuất thông tin.',
            'AI_SERVICE_ERROR' => 'Dịch vụ AI hiện không khả dụng. Vui lòng thử lại sau.',
            default            => 'Đã xảy ra lỗi trong quá trình xử lý.',
        };
    }
}
