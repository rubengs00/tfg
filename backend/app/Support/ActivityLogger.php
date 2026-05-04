<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function record(?User $user, string $action, string $resourceType, Model|int|null $resource = null, array $metadata = []): void
    {
        ActivityLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resource instanceof Model ? $resource->getKey() : $resource,
            'metadata' => $metadata ?: null,
        ]);
    }
}
