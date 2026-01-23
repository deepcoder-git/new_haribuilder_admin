<?php

namespace App\Utility\Response;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse implements Responsable
{
    public function __construct(
        public bool $isError = false,
        public int $code = Response::HTTP_OK,
        public mixed $data = null,
        public string $message = '',
        public array $headers = [],        
    ) {
    }

    public function toResponse($request): JsonResponse
    {
        $response = [
            'isError' => $this->isError,
            'code' => $this->code,
            'data' => $this->data,
            'message' => $this->message,
        ];

        $paginator = $this->extractPaginator($this->data);
        if ($paginator) {
            $response['pagination'] = [
                'page' => $paginator->currentPage(),
                'take' => $paginator->perPage(),
                'itemCount' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
                'hasPreviousPage' => $paginator->currentPage() > 1,
                'hasNextPage' => $paginator->hasMorePages(),
            ];
        }

        return response()->json($response, $this->code, $this->headers);
    }

    private function extractPaginator($data): ?LengthAwarePaginator
    {
        if ($data instanceof LengthAwarePaginator) {
            return $data;
        }

        if ($data instanceof ResourceCollection) {
            try {
                $reflection = new \ReflectionClass($data);
                $resourceProperty = $reflection->getProperty('resource');
                $resourceProperty->setAccessible(true);
                $resource = $resourceProperty->getValue($data);
                
                if ($resource instanceof LengthAwarePaginator) {
                    return $resource;
                }
            } catch (\ReflectionException $e) {
                // Fallback: try to access resource property directly
                if (property_exists($data, 'resource')) {
                    $resource = $data->resource;
                    if ($resource instanceof LengthAwarePaginator) {
                        return $resource;
                    }
                }
            }
        }

        return null;
    }
}
