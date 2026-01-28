<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\Return;

use App\Http\Controllers\Controller;
use App\Services\ReturnService;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReturnController extends Controller
{
    protected ?ReturnService $returnService = null;

    public function __construct()
    {
        $this->returnService = app(ReturnService::class);
    }

    public function index(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 10);
            $filters = $request->only(['type', 'manager_id', 'site_id', 'order_id', 'status']);

            $returns = $this->returnService->paginate($perPage, array_filter($filters));

            return new ApiResponse(
                isError: false,
                code: 200,
                data: $returns,
                message: $returns->isEmpty() ? 'No returns found' : 'Returns retrieved successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get returns failed',
                500
            );
        }
    }

    public function store(Request $request): ApiResponse|ApiErrorResponse
    {
        $data = $this->normalizePayload($request->all());

        $validator = Validator::make($data, [
            'type' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['required', 'exists:moderators,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:pending,approved,rejected,completed'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.ordered_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.return_quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_type' => ['nullable', 'string', 'max:255'],
            'items.*.adjust_stock' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'return creation failed',
                422
            );
        }

        try {
            $return = $this->returnService->create($data);
            $return->load(['manager', 'site', 'order', 'items.product']);

            return new ApiResponse(
                isError: false,
                code: 201,
                data: $return,
                message: 'Return created successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'return creation failed',
                500
            );
        }
    }

    public function show(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $return = $this->returnService->findOrFail($id);
            $return->load(['manager', 'site', 'order', 'items.product']);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: $return,
                message: 'Return retrieved successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get return failed',
                404
            );
        }
    }

    public function update(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        $data = $this->normalizePayload($request->all());

        $validator = Validator::make($data, [
            'type' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['required', 'exists:moderators,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'string', 'in:pending,approved,rejected,completed'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.ordered_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.return_quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_type' => ['nullable', 'string', 'max:255'],
            'items.*.adjust_stock' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'return update failed',
                422
            );
        }

        try {
            $return = $this->returnService->update($id, $data);
            $return->load(['manager', 'site', 'order', 'items.product']);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: $return,
                message: 'Return updated successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'return update failed',
                500
            );
        }
    }

    public function destroy(Request $request, int $id): ApiResponse|ApiErrorResponse
    {
        try {
            $this->returnService->delete($id);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: null,
                message: 'Return deleted successfully',
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'return deletion failed',
                500
            );
        }
    }

    /**
     * Normalize incoming payload so Return API can accept both:
     * - items: [ { product_id, ordered_quantity, return_quantity, ... } ]
     * - products: [ { product_id, quantity, wastage_qty|return_quantity, ... } ] (wastage-like)
     */
    protected function normalizePayload(array $data): array
    {
        // If items already present, keep as is
        if (!empty($data['items']) && is_array($data['items'])) {
            return $data;
        }

        // If products is provided (wastage-style payload), convert it to items
        if (!empty($data['products']) && is_array($data['products'])) {
            $items = [];

            foreach ($data['products'] as $row) {
                $items[] = [
                    'product_id' => $row['product_id'] ?? null,
                    'ordered_quantity' => $row['ordered_quantity'] ?? ($row['quantity'] ?? 0),
                    'return_quantity' => $row['return_quantity'] ?? ($row['wastage_qty'] ?? 0),
                    'unit_type' => $row['unit_type'] ?? null,
                    'adjust_stock' => $row['adjust_stock'] ?? false,
                ];
            }

            $data['items'] = $items;
        }

        return $data;
    }
}

