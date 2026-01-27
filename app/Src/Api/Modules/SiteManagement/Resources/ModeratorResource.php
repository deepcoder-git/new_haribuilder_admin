<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\SiteManagement\Resources;

use App\Models\Moderator;
use Illuminate\Http\Resources\Json\JsonResource;

class ModeratorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Moderator $this */

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'mobile_number' => $this->mobile_number,
            'status'        => $this->status,
            'role'          => $this->role?->value,
            'role_label'    => $this->role?->label(),
        ];
    }
}
