<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterviewActionRequest;
use App\Http\Requests\SaveInterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Models\Application;
use App\Models\Interview;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InterviewController extends Controller
{
    public function __construct(private InterviewService $interviewService)
    {
    }

    /**
     * API-INT-01: POST /api/v1/company/applications/{id}/interviews
     */
    public function schedule(SaveInterviewRequest $request, int $id): JsonResponse
    {
        $application = Application::with('job')->findOrFail($id);
        Gate::authorize('create', [Interview::class, $application]);

        $interview = $this->interviewService->schedule(
            $request->user(),
            $id,
            $request->validated()
        );

        return response()->json([
            'data' => [
                'interview'          => (new InterviewResource($interview))->resolve($request),
                'application_status' => $interview->application->status,
            ],
        ], 201);
    }

    /**
     * API-INT-02: PATCH /api/v1/company/interviews/{id}
     */
    public function update(SaveInterviewRequest $request, int $id): JsonResponse
    {
        $interview = Interview::with('application.job')->findOrFail($id);
        Gate::authorize('update', $interview);

        $updated = $this->interviewService->update(
            $request->user(),
            $id,
            $request->validated()
        );

        return response()->json([
            'data' => [
                'interview' => (new InterviewResource($updated))->resolve($request),
            ],
        ]);
    }

    /**
     * API-INT-03: POST /api/v1/company/interviews/{id}/cancel
     */
    public function cancel(InterviewActionRequest $request, int $id): JsonResponse
    {
        $interview = Interview::with('application.job')->findOrFail($id);
        Gate::authorize('cancel', $interview);

        $cancelled = $this->interviewService->cancel(
            $request->user(),
            $id,
            $request->validated('reason')
        );

        return response()->json([
            'data' => [
                'interview'          => (new InterviewResource($cancelled))->resolve($request),
                'application_status' => $cancelled->application->status,
            ],
        ]);
    }

    /**
     * API-INT-04: POST /api/v1/company/interviews/{id}/complete
     */
    public function complete(InterviewActionRequest $request, int $id): JsonResponse
    {
        $interview = Interview::with('application.job')->findOrFail($id);
        Gate::authorize('complete', $interview);

        $completed = $this->interviewService->complete(
            $request->user(),
            $id,
            $request->validated('note')
        );

        return response()->json([
            'data' => [
                'interview'          => (new InterviewResource($completed))->resolve($request),
                'application_status' => $completed->application->status,
            ],
        ]);
    }

    /**
     * API-INT-05: POST /api/v1/company/interviews/{id}/no-show
     */
    public function noShow(InterviewActionRequest $request, int $id): JsonResponse
    {
        $interview = Interview::with('application.job')->findOrFail($id);
        Gate::authorize('noShow', $interview);

        $noShowInterview = $this->interviewService->noShow(
            $request->user(),
            $id,
            $request->validated('note')
        );

        return response()->json([
            'data' => [
                'interview'          => (new InterviewResource($noShowInterview))->resolve($request),
                'application_status' => $noShowInterview->application->status,
            ],
        ]);
    }
}
