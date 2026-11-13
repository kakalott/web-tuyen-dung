<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogItemRequest;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService
    ) {}

    /**
     * API-CAT-01: Liệt kê danh sách kỹ năng chuẩn kèm aliases (phân trang).
     */
    public function skillsIndex(Request $request): JsonResponse
    {
        $paginator = $this->catalogService->skillsIndex($request->query());

        return response()->json($paginator);
    }

    /**
     * API-CAT-02: Thêm mới kỹ năng chuẩn.
     */
    public function skillStore(CatalogItemRequest $request): JsonResponse
    {
        $skill = $this->catalogService->saveSkill($request->validated());

        return response()->json([
            'data' => $skill,
        ], 201);
    }

    /**
     * API-CAT-03: Cập nhật tên hoặc bật/tắt trạng thái kỹ năng chuẩn.
     */
    public function skillUpdate(CatalogItemRequest $request, int $id): JsonResponse
    {
        $skill = $this->catalogService->saveSkill($request->validated(), $id);

        return response()->json([
            'data' => $skill,
        ]);
    }

    /**
     * API-CAT-04: Thêm biến thể (alias) cho kỹ năng chuẩn.
     */
    public function aliasStore(CatalogItemRequest $request, int $id): JsonResponse
    {
        $alias = $this->catalogService->saveAlias($id, $request->validated());

        return response()->json([
            'data' => $alias,
        ], 201);
    }

    /**
     * API-CAT-05: Cập nhật tên hoặc trạng thái biến thể (alias).
     */
    public function aliasUpdate(CatalogItemRequest $request, int $id): JsonResponse
    {
        $alias = $this->catalogService->updateAlias($id, $request->validated());

        return response()->json([
            'data' => $alias,
        ]);
    }

    /**
     * API-CAT-07: Liệt kê danh mục tuyển dụng động (phân trang).
     */
    public function lookupIndex(Request $request, string $catalog): JsonResponse
    {
        $paginator = $this->catalogService->lookupIndex($catalog, $request->query());

        return response()->json($paginator);
    }

    /**
     * API-CAT-08: Thêm mới mục trong danh mục tuyển dụng động.
     */
    public function lookupStore(CatalogItemRequest $request, string $catalog): JsonResponse
    {
        $item = $this->catalogService->lookupStore($catalog, $request->validated());

        return response()->json([
            'data' => $item,
        ], 201);
    }

    /**
     * API-CAT-09: Cập nhật tên hoặc bật/tắt mục danh mục tuyển dụng động.
     */
    public function lookupUpdate(CatalogItemRequest $request, string $catalog, int $id): JsonResponse
    {
        $item = $this->catalogService->lookupUpdate($catalog, $id, $request->validated());

        return response()->json([
            'data' => $item,
        ]);
    }
}
