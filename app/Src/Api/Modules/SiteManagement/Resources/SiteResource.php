<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'start_date' => Carbon::parse($this->start_date)->format('d-m-Y'),
            'end_date' => Carbon::parse($this->end_date)->format('d-m-Y'),
            'type' => $this->type,
            'status' => ($this->status) ? 'Active' : 'Deactive',
        ];
    }
}
