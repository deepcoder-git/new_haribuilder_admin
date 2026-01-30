<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\WastageReturn;

use App\Http\Controllers\Controller;
use App\Models\Wastage;
use App\Models\OrderReturn;
use App\Services\WastageService;
use App\Services\ReturnService;
use App\Utility\Enums\CreatorTypeEnum;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WastageReturnController extends Controller
{
    protected ?WastageService $wastageService = null;
    protected ?ReturnService $returnService = null;

    public function __construct()
    {
        $this->wastageService = app(WastageService::class);
        $this->returnService = app(ReturnService::class);
    }

    /**
     * Create wastage or return based on type
     * 
     * @param Request $request
     * @return ApiResponse|ApiErrorResponse
     */
    public function store(Request $request): ApiResponse|ApiErrorResponse
    {
        $data = $request->all();
        $type = $data['type'] ?? null;

        // Validate type is provided and valid
        if (!$type || !in_array($type, ['wastage', 'return'], true)) {
            return new ApiErrorResponse(
                ['errors' => ['type field is required and must be either "wastage" or "return"']],
                'validation failed',
                422
            );
        }

        // Normalize and validate based on type
        if ($type === 'wastage') {
            return $this->createWastage($request, $data);
        } else {
            return $this->createReturn($request, $data);
        }
    }

    /**
     * Create wastage
     * Handles: Site Wastage, Store Wastage, Order-wise Wastage, General Wastage
     */
    protected function createWastage(Request $request, array $data): ApiResponse|ApiErrorResponse
    {
        // Get authenticated user and auto-populate manager_id from logged-in user (moderator table id)
        $user = $request->user();
        if (!$user) {
            return new ApiErrorResponse(
                ['errors' => ['User must be authenticated']],
                'authentication failed',
                401
            );
        }

        // Auto-populate manager_id from authenticated user (moderator table id)
        // Always use logged-in user's id, regardless of role
        $data['manager_id'] = $user->id;

        // Set default creator_type if not provided (can be overridden in request)
        if (!isset($data['creator_type'])) {
            $data['creator_type'] = CreatorTypeEnum::Other->value;
        }

        // Normalize payload: convert 'items' to 'products' if needed for backward compatibility
        if (isset($data['items']) && !isset($data['products'])) {
            $data['products'] = array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'] ?? null,
                    'quantity' => $item['ordered_quantity'] ?? $item['quantity'] ?? 1,
                    'wastage_qty' => $item['wastage_qty'] ?? $item['return_quantity'] ?? 1,
                    'unit_type' => $item['unit_type'] ?? null,
                    'adjust_stock' => $item['adjust_stock'] ?? true,
                ];
            }, $data['items']);
            unset($data['items']);
        }

        // Extract wastage_type from request
        // The main 'type' field is 'wastage' (already used for routing), 
        // we need 'wastage_type' which is site_wastage or store_wastage
        $wastageType = $data['wastage_type'] ?? null;
        
        // Validate wastage_type is provided
        if (!$wastageType || !in_array($wastageType, ['site_wastage', 'store_wastage'], true)) {
            return new ApiErrorResponse(
                ['errors' => ['wastage_type field is required and must be either "site_wastage" or "store_wastage"']],
                'wastage creation failed',
                422
            );
        }
        
        // Set the wastage type (this will be saved to database)
        $data['type'] = $wastageType;

        // Build validation rules based on wastage type
        $rules = [
            'type' => 'required|string|in:site_wastage,store_wastage',
            'manager_id' => 'required|exists:moderators,id',
            'creator_type' => 'nullable|string|in:store_manager,site_manager,other',
            'date' => 'required|date',
            'reason' => 'nullable|string|max:1000',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.wastage_qty' => 'required|integer|min:1',
            'products.*.unit_type' => 'nullable|string',
            'products.*.adjust_stock' => 'sometimes|boolean',
        ];

        // Site wastage: site_id is required
        if ($wastageType === 'site_wastage') {
            $rules['site_id'] = 'required|exists:sites,id';
            $rules['order_id'] = 'nullable|exists:orders,id';
        } 
        // Store wastage: site_id is optional
        elseif ($wastageType === 'store_wastage') {
            $rules['site_id'] = 'nullable|exists:sites,id';
            $rules['order_id'] = 'nullable|exists:orders,id';
        }

        // Order-wise wastage: validate wastage_qty doesn't exceed ordered qty
        // This validation happens in WastageService, but we ensure order_id is valid
        if (!empty($data['order_id'])) {
            $rules['order_id'] = 'required|exists:orders,id';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'wastage creation failed',
                422
            );
        }

        try {
            $wastage = $this->wastageService->create($data);
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

    /**
     * Create return
     */
    protected function createReturn(Request $request, array $data): ApiResponse|ApiErrorResponse
    {
        // Get authenticated user and auto-populate manager_id from logged-in user (moderator table id)
        $user = $request->user();
        if (!$user) {
            return new ApiErrorResponse(
                ['errors' => ['User must be authenticated']],
                'authentication failed',
                401
            );
        }

        // Auto-populate manager_id from authenticated user (moderator table id)
        // Always use logged-in user's id, regardless of role
        $data['manager_id'] = $user->id;

        // Set default creator_type if not provided (can be overridden in request)
        if (!isset($data['creator_type'])) {
            $data['creator_type'] = CreatorTypeEnum::Other->value;
        }

        // Normalize payload: convert 'products' to 'items' if needed for backward compatibility
        if (isset($data['products']) && !isset($data['items'])) {
            $data['items'] = array_map(function ($product) {
                return [
                    'product_id' => $product['product_id'] ?? null,
                    'ordered_quantity' => $product['quantity'] ?? $product['ordered_quantity'] ?? 0,
                    'return_quantity' => $product['return_quantity'] ?? $product['wastage_qty'] ?? 1,
                    'unit_type' => $product['unit_type'] ?? null,
                    'adjust_stock' => $product['adjust_stock'] ?? true,
                ];
            }, $data['products']);
            unset($data['products']);
        }

        // Remove wastage-specific type field if present, keep return type if provided
        $returnType = $data['return_type'] ?? null;
        if (isset($data['type'])) {
            if (in_array($data['type'], ['site_wastage', 'store_wastage'], true)) {
                unset($data['type']);
            } elseif (!empty($data['type']) && $data['type'] !== 'return') {
                // Keep custom return type
                $returnType = $data['type'];
            }
        }
        
        if ($returnType) {
            $data['type'] = $returnType;
        }

        // Build validation rules
        $rules = [
            'type' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['required', 'exists:moderators,id'],
            'creator_type' => ['nullable', 'string', 'in:store_manager,site_manager,other'],
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
        ];

        // Order-wise return: validate return_quantity doesn't exceed ordered_quantity
        // This validation happens in ReturnService, but we ensure order_id is valid
        if (!empty($data['order_id'])) {
            $rules['order_id'] = 'required|exists:orders,id';
        }

        $validator = Validator::make($data, $rules);

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

    /**
     * Get list of wastages or returns based on type filter
     */
    public function index(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 10);
            $type = $request->get('type'); // 'wastage' or 'return'
            $filters = $request->only(['manager_id', 'site_id', 'order_id', 'status']);

            if ($type === 'wastage') {
                // Get wastages
                $filters['type'] = $request->get('wastage_type'); // site_wastage or store_wastage
                $results = $this->wastageService->paginate($perPage, array_filter($filters));
                $message = $results->isEmpty() ? 'No wastages found' : 'Wastages retrieved successfully';
            } elseif ($type === 'return') {
                // Get returns
                $filters['type'] = $request->get('return_type');
                $results = $this->returnService->paginate($perPage, array_filter($filters));
                $message = $results->isEmpty() ? 'No returns found' : 'Returns retrieved successfully';
            } else {
                // Get both wastages and returns
                $wastageFilters = array_merge($filters, ['type' => $request->get('wastage_type')]);
                $returnFilters = array_merge($filters, ['type' => $request->get('return_type')]);
                
                $wastages = $this->wastageService->paginate($perPage, array_filter($wastageFilters));
                $returns = $this->returnService->paginate($perPage, array_filter($returnFilters));
                
                $results = [
                    'wastages' => $wastages,
                    'returns' => $returns,
                ];
                $message = 'Wastages and returns retrieved successfully';
            }

            return new ApiResponse(
                isError: false,
                code: 200,
                data: $results,
                message: $message,
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get wastages/returns failed',
                500
            );
        }
    }
}
