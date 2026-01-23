<?php

namespace App\Utility\Response;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ApiValidationFailResponse implements Responsable
{
    public function __construct(protected ValidationException $e)
    {
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'isError' => true,
            'code' => 422,
            'data' => [
                'errors' => $this->e->errors(),
            ],
            'message' => $this->e->getMessage() ?: 'Validation failed',
        ], 422);
    }
}
