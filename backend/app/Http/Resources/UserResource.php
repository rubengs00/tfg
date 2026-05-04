<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'avatarUrl' => $this->avatar_url,
            'twoFactorEnabled' => $this->two_factor_enabled,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
