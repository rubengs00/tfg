<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'resourceType' => $this->resource_type,
            'resourceId' => $this->resource_id,
            'metadata' => $this->metadata,
            'user' => new UserResource($this->whenLoaded('user')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
