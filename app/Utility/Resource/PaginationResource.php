<?php

namespace App\Utility\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaginationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $take = $request->get('per_page') ?? 10;

        return [
            'page' => (int) $request->get('page') ?? 1,
            'take' => $this->perPage(),
            'itemCount' => $this->total(),
            'pageCount' => ceil($this->total() / $take),
            'hasPreviousPage' => $this->total() && ! $this->onFirstPage(),
            'hasNextPage' => $this->hasMorePages(),
        ];
    }
}
