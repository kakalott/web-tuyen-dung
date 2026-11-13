<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewJobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminJobController extends Controller
{
    public function __construct(private AdminService $adminService)
    {
    }

    /**
     * API-ADM-04: GET /api/v1/admin/jobs/pending
     */
    public function pendingJobs(Request $request): JsonResponse
    {
        $paginator = $this->adminService->listPendingJobs($request->only(['page', 'page_size']));

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
     * API-ADM-05: GET /api/v1/admin/jobs/{id}
     */
    public function showJob(Request $request, int $id): JsonResponse
    {
        $job = $this->adminService->showJob($id);

        return response()->json([
            'data' => (new JobResource($job))->resolve($request),
        ]);
    }

    /**
     * API-ADM-06: PATCH /api/v1/admin/jobs/{id}/review
     */
    public function reviewJob(ReviewJobRequest $request, int $id): JsonResponse
    {
        $job = Job::findOrFail($id);
        Gate::authorize('review', $job);

        $reviewedJob = $this->adminService->reviewJob(
            $request->user(),
            $id,
            $request->validated('decision'),
            $request->validated('reason')
        );

        return response()->json([
            'data' => (new JobResource($reviewedJob))->resolve($request),
        ]);
    }

    /**
     * API-ADM-07: PATCH /api/v1/admin/jobs/{id}/hide
     */
    public function hideJob(ReviewJobRequest $request, int $id): JsonResponse
    {
        $job = Job::findOrFail($id);
        Gate::authorize('hide', $job);

        $hiddenJob = $this->adminService->hideJob(
            $request->user(),
            $id,
            $request->validated('reason')
        );

        return response()->json([
            'data' => (new JobResource($hiddenJob))->resolve($request),
        ]);
    }
}
