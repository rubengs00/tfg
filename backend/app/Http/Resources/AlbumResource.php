<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spotifyId' => $this->spotify_id,
            'artistId' => $this->artist_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'coverUrl' => $this->cover_url,
            'releaseYear' => $this->release_year,
            'totalTracks' => $this->total_tracks,
            'artist' => new ArtistResource($this->whenLoaded('artist')),
            'songs' => SongResource::collection($this->whenLoaded('songs')),
        ];
    }
}
