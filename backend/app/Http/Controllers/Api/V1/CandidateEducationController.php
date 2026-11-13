<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEducationRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateEducationController extends Controller
{
    public function __construct(private CandidateProfileService $profileService)
    {
    }

    public function store(SaveEducationRequest $request): JsonResponse
    {
        $education = $this->profileService->saveEducation($request->user(), $request->validated());

        return response()->json([
            'data' => $education,
        ], 201);
    }

    public function update(SaveEducationRequest $request, int $id): JsonResponse
    {
        $education = $this->profileService->saveEducation($request->user(), $request->validated(), $id);

        return response()->json([
            'data' => $education,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->profileService->deleteEducation($request->user(), $id);

        return response()->json([
            'data' => [
                'deleted' => true,
            ],
        ]);
    }
}
