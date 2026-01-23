<?php

namespace App\Utility\Response;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use stdClass;

class ApiErrorResponse implements Responsable
{
    public function __construct(
        public mixed $data = null,
        public string $message,
        public int $code,
        public array $headers = [],
    ) {
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'isError' => true,
            'code' => $this->code,
            'data' => $this->data,
            'message' => $this->message,
        ], $this->code, $this->headers);
    }
}
