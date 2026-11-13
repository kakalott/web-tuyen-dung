<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyJobController extends Controller
{
    public function __construct(private JobService $jobService)
    {
    }

    /**
     * API-JOB-03: GET /api/v1/company/jobs
     */
    public function indexOwn(Request $request): JsonResponse
    {
        $paginator = $this->jobService->companyList(
            $request->user(),
            $request->only(['review_status', 'publication_status', 'page', 'page_size'])
        );

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
     * API-JOB-04: POST /api/v1/company/jobs
     */
    public function store(SaveJobRequest $request): JsonResponse
    {
        Gate::authorize('create', Job::class);

        $job = $this->jobService->store($request->user(), $request->validated());

        return response()->json([
            'data' => (new JobResource($job))->resolve($request),
        ], 201);
    }

    /**
     * API-JOB-05: GET /api/v1/company/jobs/{jobId}
     */
    public function showOwn(Request $request, int $jobId): JsonResponse
    {
        $job = Job::findOrFail($jobId);
        Gate::authorize('viewCompanyJob', $job);

        $job = $this->jobService->companyShow($request->user(), $job);

        return response()->json([
            'data' => (new JobResource($job))->resolve($request),
        ]);
    }

    /**
     * API-JOB-06: PATCH /api/v1/company/jobs/{jobId}
     */
    public function update(SaveJobRequest $request, int $jobId): JsonResponse
    {
        $job = Job::findOrFail($jobId);
        Gate::authorize('update', $job);

        $updatedJob = $this->jobService->update($request->user(), $job, $request->validated());

        return response()->json([
            'data' => (new JobResource($updatedJob))->resolve($request),
        ]);
    }

    /**
     * API-JOB-07: POST /api/v1/company/jobs/{jobId}/close
     */
    public function close(Request $request, int $jobId): JsonResponse
    {
        $job = Job::findOrFail($jobId);
        Gate::authorize('close', $job);

        $closedJob = $this->jobService->close($request->user(), $job);

        return response()->json([
            'data' => (new JobResource($closedJob))->resolve($request),
        ]);
    }
}
