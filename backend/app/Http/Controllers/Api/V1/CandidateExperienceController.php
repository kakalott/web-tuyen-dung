<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveExperienceRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateExperienceController extends Controller
{
    public function __construct(private CandidateProfileService $profileService)
    {
    }

    public function store(SaveExperienceRequest $request): JsonResponse
    {
        $experience = $this->profileService->saveExperience($request->user(), $request->validated());

        return response()->json([
            'data' => $experience,
        ], 201);
    }

    public function update(SaveExperienceRequest $request, int $id): JsonResponse
    {
        $experience = $this->profileService->saveExperience($request->user(), $request->validated(), $id);

        return response()->json([
            'data' => $experience,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->profileService->deleteExperience($request->user(), $id);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }
}
