<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminCompanyController extends Controller
{
    public function __construct(private AdminService $adminService)
    {
    }

    /**
     * API-ADM-01: GET /api/v1/admin/companies/unverified
     */
    public function unverified(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $pageSize = (int) $request->query('page_size', 15);

        $paginator = $this->adminService->listUnverifiedCompanies($page, $pageSize);

        return response()->json([
            'data' => CompanyResource::collection($paginator->items())->toArray($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'page_size'    => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * API-ADM-02: GET /api/v1/admin/companies/{companyId}
     */
    public function showCompany(Request $request, int $companyId): JsonResponse
    {
        $company = $this->adminService->showCompany($companyId);

        return response()->json([
            'data' => (new CompanyResource($company))->toArray($request),
        ]);
    }

    /**
     * API-ADM-03: PATCH /api/v1/admin/companies/{companyId}/verification
     */
    public function verify(VerifyCompanyRequest $request, int $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);
        Gate::authorize('verify', $company);

        $verifiedCompany = $this->adminService->verifyCompany(
            $request->user(),
            $companyId,
            $request->validated()
        );

        return response()->json([
            'data' => (new CompanyResource($verifiedCompany))->toArray($request),
        ]);
    }
}
