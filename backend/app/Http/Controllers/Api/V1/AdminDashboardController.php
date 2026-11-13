<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogItemRequest;
use App\Http\Resources\AdminDashboardResource;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminService $adminService
    ) {}

    /**
     * API-ADM-08: Admin Dashboard Statistics
     */
    public function dashboard(): JsonResponse
    {
        $stats = $this->adminService->dashboard();

        return response()->json([
            'data' => (new AdminDashboardResource($stats))->resolve(),
        ]);
    }
}
