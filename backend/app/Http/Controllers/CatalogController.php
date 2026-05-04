<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlbumResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\SongResource;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\SpotifyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function __construct(private readonly SpotifyCatalogService $spotify)
    {
    }

    public function home(): JsonResponse
    {
        return response()->json([
            'artists' => ArtistResource::collection(
                Artist::query()->orderByDesc('popularity')->limit(8)->get()
            ),
            'albums' => AlbumResource::collection(
                Album::query()->with('artist')->latest()->limit(8)->get()
            ),
            'songs' => SongResource::collection(
                Song::query()->with('album.artist')->orderByDesc('popularity')->limit(10)->get()
            ),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['source' => 'local', 'artists' => [], 'albums' => [], 'songs' => []]);
        }

        $spotifyResults = $this->spotify->search($query);

        if ($spotifyResults !== null) {
            return response()->json([
                'source' => $spotifyResults['source'],
                'artists' => ArtistResource::collection($spotifyResults['artists']),
                'albums' => AlbumResource::collection($spotifyResults['albums']),
                'songs' => SongResource::collection($spotifyResults['songs']),
            ]);
        }

        return response()->json([
            'source' => 'local',
            'artists' => ArtistResource::collection(
                Artist::query()
                    ->where('name', 'like', "%{$query}%")
                    ->orWhere('genre', 'like', "%{$query}%")
                    ->limit(8)
                    ->get()
            ),
            'albums' => AlbumResource::collection(
                Album::query()
                    ->with('artist')
                    ->where('title', 'like', "%{$query}%")
                    ->limit(8)
                    ->get()
            ),
            'songs' => SongResource::collection(
                Song::query()
                    ->with('album.artist')
                    ->where('title', 'like', "%{$query}%")
                    ->limit(10)
                    ->get()
            ),
        ]);
    }

    public function artists(): AnonymousResourceCollection
    {
        return ArtistResource::collection(
            Artist::query()->orderByDesc('popularity')->paginate(20)
        );
    }

    public function artist(Artist $artist): ArtistResource
    {
        return new ArtistResource($artist->load('albums.songs'));
    }

    public function albums(): AnonymousResourceCollection
    {
        return AlbumResource::collection(
            Album::query()->with('artist')->latest()->paginate(20)
        );
    }

    public function album(Album $album): AlbumResource
    {
        return new AlbumResource($album->load('artist', 'songs.album.artist'));
    }

    public function songs(): AnonymousResourceCollection
    {
        return SongResource::collection(
            Song::query()->with('album.artist')->orderByDesc('popularity')->paginate(30)
        );
    }
}
