<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyProfileController extends Controller
{
    public function __construct(private CompanyService $companyService)
    {
    }

    /**
     * API-COM-01: GET /api/v1/company/profile
     */
    public function show(Request $request): JsonResponse
    {
        $company = $this->companyService->show($request->user());
        Gate::authorize('view', $company);

        return response()->json([
            'data' => (new CompanyResource($company))->toArray($request),
        ]);
    }

    /**
     * API-COM-02: PATCH /api/v1/company/profile
     */
    public function update(UpdateCompanyProfileRequest $request): JsonResponse
    {
        $company = $this->companyService->show($request->user());
        Gate::authorize('update', $company);

        $updatedCompany = $this->companyService->update(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'data' => (new CompanyResource($updatedCompany))->toArray($request),
        ]);
    }
}
