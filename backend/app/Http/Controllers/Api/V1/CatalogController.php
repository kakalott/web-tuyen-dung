<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalogService)
    {
    }

    /**
     * API-CAT-06: GET /api/v1/catalog/lookups
     */
    public function publicLookups(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogService->publicLookups(),
        ]);
    }
}
