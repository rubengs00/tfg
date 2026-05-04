<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spotifyId' => $this->spotify_id,
            'albumId' => $this->album_id,
            'title' => $this->title,
            'durationSeconds' => $this->duration_seconds,
            'previewUrl' => $this->preview_url,
            'trackNumber' => $this->track_number,
            'explicit' => $this->explicit,
            'popularity' => $this->popularity,
            'album' => new AlbumResource($this->whenLoaded('album')),
            'isFavorite' => (bool) ($this->is_favorite ?? false),
        ];
    }
}
