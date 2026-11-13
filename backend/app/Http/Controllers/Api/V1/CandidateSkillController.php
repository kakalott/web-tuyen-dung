<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncCandidateSkillsRequest;
use App\Http\Resources\SkillResource;
use App\Services\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateSkillController extends Controller
{
    public function __construct(private SkillService $skillService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $skills = $this->skillService->listCandidateSkills($request->user());

        return response()->json([
            'data' => SkillResource::collection($skills)->resolve($request),
        ]);
    }

    public function sync(SyncCandidateSkillsRequest $request): JsonResponse
    {
        $syncedIds = $this->skillService->syncCandidate(
            $request->user(),
            $request->validated('skill_ids')
        );

        return response()->json([
            'data' => [
                'skill_ids' => $syncedIds->values(),
            ],
        ]);
    }
}
