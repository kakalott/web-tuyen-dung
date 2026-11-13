<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SkillResource;
use App\Services\SkillService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __construct(private SkillService $skillService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->skillService->index($request->only(['search', 'page', 'page_size']));

        if ($result instanceof LengthAwarePaginator) {
            return response()->json([
                'data' => SkillResource::collection($result->items())->resolve($request),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'page_size'    => $result->perPage(),
                    'total'        => $result->total(),
                    'last_page'    => $result->lastPage(),
                ],
            ]);
        }

        return response()->json([
            'data' => SkillResource::collection($result)->resolve($request),
        ]);
    }
}
