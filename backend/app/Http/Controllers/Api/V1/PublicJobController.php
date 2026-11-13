<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchJobsRequest;
use App\Http\Resources\JobResource;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;

class PublicJobController extends Controller
{
    public function __construct(private JobService $jobService)
    {
    }

    /**
     * API-JOB-01: GET /api/v1/jobs
     */
    public function index(SearchJobsRequest $request): JsonResponse
    {
        $paginator = $this->jobService->publicList($request->validated());

        return response()->json([
            'data' => [
                'items' => JobResource::collection($paginator->items())->resolve($request),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'page_size'    => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * API-JOB-02: GET /api/v1/jobs/{jobId}
     */
    public function show(int $jobId): JsonResponse
    {
        $job = $this->jobService->publicShow($jobId);

        return response()->json([
            'data' => (new JobResource($job))->resolve(request()),
        ]);
    }
}
