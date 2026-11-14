<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyToJobRequest;
use App\Http\Requests\ListQueryRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CandidateApplicationController extends Controller
{
    public function __construct(private ApplicationService $applicationService)
    {
    }

    /**
     * API-APP-01: POST /api/v1/jobs/{jobId}/applications
     */
    public function apply(ApplyToJobRequest $request, int $jobId): JsonResponse
    {
        $application = $this->applicationService->apply(
            $request->user(),
            $jobId,
            (int) $request->validated('cv_id')
        );

        return response()->json([
            'data' => [
                'application_id' => $application->id,
                'status'         => $application->status,
                'applied_at'     => $application->applied_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * API-APP-02: GET /api/v1/candidate/applications
     */
    public function index(ListQueryRequest $request): JsonResponse
    {
        $paginator = $this->applicationService->listForCandidate(
            $request->user(),
            $request->validated()
        );

        $items = collect($paginator->items())->map(fn (Application $app) => [
            'id'           => $app->id,
            'job_id'       => $app->job_id,
            'job_title'    => $app->job?->title,
            'company_name' => $app->job?->company?->company_name,
            'cv_id'        => $app->cv_id,
            'cv_filename'  => $app->cv?->original_filename ?? $app->candidateCV?->original_filename,
            'status'       => $app->status,
            'applied_at'   => $app->applied_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'data' => [
                'items' => $items,
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
     * API-APP-03: GET /api/v1/candidate/applications/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        Gate::authorize('viewCandidateApplication', $application);

        $detail = $this->applicationService->detailForCandidate($application);
        $resource = (new ApplicationResource($detail))->resolve($request);

        return response()->json([
            'data' => [
                'application' => collect($resource)->except(['interviews'])->all(),
                'interviews'  => $resource['interviews'] ?? [],
            ],
        ]);
    }

    /**
     * API-APP-04: POST /api/v1/candidate/applications/{id}/withdraw
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        Gate::authorize('withdraw', $application);

        $updated = $this->applicationService->withdraw($request->user(), $id);

        return response()->json([
            'data' => [
                'status'       => $updated->status,
                'withdrawn_at' => $updated->withdrawn_at?->toIso8601String(),
            ],
        ]);
    }
}
