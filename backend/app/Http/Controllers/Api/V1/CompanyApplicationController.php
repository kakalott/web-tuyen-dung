<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeApplicationStatusRequest;
use App\Http\Requests\ListQueryRequest;
use App\Http\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Job;
use App\Services\ApplicationService;
use App\Services\CVService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyApplicationController extends Controller
{
    public function __construct(
        private ApplicationService $applicationService,
        private CVService $cvService
    ) {
    }

    /**
     * API-APP-05: GET /api/v1/company/jobs/{jobId}/applications
     */
    public function indexByJob(ListQueryRequest $request, int $jobId): JsonResponse
    {
        $job = Job::findOrFail($jobId);
        Gate::authorize('viewJobApplications', $job);

        $paginator = $this->applicationService->listForJob($job, $request->validated());

        $items = collect($paginator->items())->map(fn (Application $app) => [
            'id'             => $app->id,
            'candidate_name' => $app->candidateProfile?->user?->name,
            'status'         => $app->status,
            'applied_at'     => $app->applied_at?->toIso8601String(),
            'match_score'    => $app->latestMatchResult ? (float) $app->latestMatchResult->match_score : null,
            'skill_coverage' => $app->latestMatchResult?->skill_coverage !== null
                ? (float) $app->latestMatchResult->skill_coverage
                : null,
            'score_stale'    => (bool) ($app->score_stale ?? false),
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
     * API-APP-06: GET /api/v1/company/applications/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $application = Application::with('job')->findOrFail($id);
        Gate::authorize('viewCompanyApplication', $application);

        $detail = $this->applicationService->detailForCompany($application);
        $resource = (new ApplicationResource($detail))->resolve($request);

        return response()->json([
            'data' => [
                'application'  => collect($resource)->except(['interviews', 'match_result'])->all(),
                'interviews'   => $resource['interviews'] ?? [],
                'match_result' => $resource['match_result'] ?? null,
            ],
        ]);
    }

    /**
     * API-APP-07: GET /api/v1/company/applications/{id}/cv
     */
    public function downloadForApplication(Request $request, int $id): StreamedResponse
    {
        $application = Application::with('job')->findOrFail($id);
        Gate::authorize('downloadCv', $application);

        return $this->cvService->downloadForApplication($request->user(), $id);
    }

    /**
     * API-APP-08: PATCH /api/v1/company/applications/{id}/status
     */
    public function changeStatus(ChangeApplicationStatusRequest $request, int $id): JsonResponse
    {
        $application = Application::with('job')->findOrFail($id);
        Gate::authorize('updateStatus', $application);

        $updated = $this->applicationService->changeStatus(
            $request->user(),
            $id,
            $request->validated('new_status'),
            $request->validated('note')
        );

        return response()->json([
            'data' => [
                'application_id' => $updated->id,
                'status'         => $updated->status,
            ],
        ]);
    }
}
