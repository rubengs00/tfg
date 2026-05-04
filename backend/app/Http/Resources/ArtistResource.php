<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spotifyId' => $this->spotify_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'genre' => $this->genre,
            'followers' => $this->followers,
            'imageUrl' => $this->image_url,
            'bio' => $this->bio,
            'popularity' => $this->popularity,
            'albums' => AlbumResource::collection($this->whenLoaded('albums')),
            'isFollowed' => (bool) ($this->is_followed ?? false),
        ];
    }
}
