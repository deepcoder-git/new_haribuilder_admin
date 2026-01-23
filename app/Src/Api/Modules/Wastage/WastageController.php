<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\Wastage;

use App\Http\Controllers\Controller;
use App\Models\Wastage;
use App\Services\WastageService;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WastageController extends Controller
{
    protected ?WastageService $wastageService = null;

    public function __construct()
    {
        $this->wastageService = app(WastageService::class);
    }

    public function index(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $filters = $request->only(['type', 'manager_id', 'site_id', 'order_id']);
            
            $wastages = $this->wastageService->paginate($perPage, array_filter($filters));
            
            return new ApiResponse(
                isError: false,
                code: 200,
                data: $wastages,
                message: $wastages->isEmpty() ? 'No wastages found' : 'Wastages retrieved successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get wastages failed',
                500
            );
        }
    }

    public function store(Request $request): ApiResponse|ApiErrorResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:site_wastage,store_wastage',
            'manager_id' => 'required|exists:moderators,id',
            'site_id' => 'nullable|exists:sites,id',
            'order_id' => 'nullable|exists:orders,id',
            'date' => 'required|date',
            'reason' => 'nullable|string|max:1000',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.wastage_qty' => 'required|integer|min:1',
            'products.*.unit_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'wastage creation failed',
                422
            );
        }

        try {
            $wastage = $this->wastageService->create($request->all());
            $wastage->load(['manager', 'site', 'order', 'products.category']);

            return new ApiResponse(
                isError: false,
                code: 201,
                data: $wastage,
                message: 'Wastage created successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'wastage creation failed',
                500
            );
        }
    }

    public function show(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $wastage = $this->wastageService->findOrFail($id);
            $wastage->load(['manager', 'site', 'order', 'products.category']);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: $wastage,
                message: 'Wastage retrieved successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get wastage failed',
                404
            );
        }
    }

    public function update(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|string|in:site_wastage,store_wastage',
            'manager_id' => 'sometimes|required|exists:moderators,id',
            'site_id' => 'nullable|exists:sites,id',
            'order_id' => 'nullable|exists:orders,id',
            'date' => 'sometimes|required|date',
            'reason' => 'nullable|string|max:1000',
            'products' => 'sometimes|required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.wastage_qty' => 'required|integer|min:1',
            'products.*.unit_type' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'wastage update failed',
                422
            );
        }

        try {
            $wastage = $this->wastageService->update($id, $request->all());
            $wastage->load(['manager', 'site', 'order', 'products.category']);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: $wastage,
                message: 'Wastage updated successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'wastage update failed',
                500
            );
        }
    }

    public function destroy(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $this->wastageService->delete($id);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: null,
                message: 'Wastage deleted successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'wastage deletion failed',
                500
            );
        }
    }
}
