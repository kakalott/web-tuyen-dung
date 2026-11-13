<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCandidateProfileRequest;
use App\Http\Resources\CandidateProfileResource;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateProfileController extends Controller
{
    public function __construct(private CandidateProfileService $profileService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $result = $this->profileService->show($request->user());

        return response()->json([
            'data' => (new CandidateProfileResource($result))->toArray($request),
        ]);
    }

    public function update(UpdateCandidateProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->update($request->user(), $request->validated());

        return response()->json([
            'data' => (new CandidateProfileResource($profile))->toArray($request),
        ]);
    }
}
