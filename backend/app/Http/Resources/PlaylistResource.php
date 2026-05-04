<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'coverUrl' => $this->cover_url,
            'isPublic' => $this->is_public,
            'songsCount' => $this->songs_count ?? $this->songs()->count(),
            'songs' => SongResource::collection($this->whenLoaded('songs')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
