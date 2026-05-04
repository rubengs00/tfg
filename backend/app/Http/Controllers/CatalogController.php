<?php

namespace App\Http\Controllers;

use App\Models\Artist as LocalArtist;
use App\Services\SpotifyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify
    ) {
    }

    public function home(): JsonResponse
    {
        $genres = [
            'rock',
            'pop',
            'hip hop',
            'indie',
            'electronic',
            'latin',
            'k-pop',
            'trap',
            'metal',
            'jazz',
        ];

        $genre = $genres[array_rand($genres)];

        try {
            $results = $this->spotify->search("genre:{$genre}");
        } catch (\Throwable $exception) {
            $results = [
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ];
        }

        return response()->json([
            'source' => 'spotify',
            'genre' => $genre,
            'artists' => $results['artists'],
            'albums' => $results['albums'],
            'tracks' => $results['tracks'],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json([
                'source' => 'spotify',
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ]);
        }

        try {
            $results = $this->spotify->search($query);
        } catch (\Throwable $exception) {
            $results = [
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ];
        }

        return response()->json([
            'source' => 'spotify',
            'artists' => $results['artists'],
            'albums' => $results['albums'],
            'tracks' => $results['tracks'],
        ]);
    }

    public function artist(string $spotifyId): JsonResponse
    {
        try {
            $artist = $this->spotify->getArtist($spotifyId);
            $albums = $this->spotify->getArtistAlbums($spotifyId);
        } catch (\Throwable $exception) {
            return response()->json([
                'artist' => null,
                'albums' => [],
                'message' => 'No se ha podido cargar el artista desde Spotify.',
                'spotifyEnabled' => $this->spotify->enabled(),
            ]);
        }

        $artist = $this->mergeLocalFollowers($spotifyId, $artist);

        return response()->json([
            'artist' => $artist ?: null,
            'albums' => $albums,
        ]);
    }

    public function album(string $spotifyId): JsonResponse
    {
        try {
            $album = $this->spotify->getAlbum($spotifyId);
            $tracks = $this->spotify->getAlbumTracks($spotifyId);
        } catch (\Throwable $exception) {
            return response()->json([
                'album' => null,
                'tracks' => [],
                'message' => 'No se ha podido cargar el album desde Spotify.',
                'spotifyEnabled' => $this->spotify->enabled(),
            ]);
        }

        return response()->json([
            'album' => $album ?: null,
            'tracks' => $tracks,
        ]);
    }

    private function mergeLocalFollowers(string $spotifyId, array $artist): array
    {
        if ($artist === []) {
            return [];
        }

        $localArtist = LocalArtist::query()
            ->where('spotify_id', $spotifyId)
            ->withCount('followedByUsers')
            ->first();

        if (! $localArtist) {
            return $artist;
        }

        $baseFollowers = max(
            (int) ($artist['followers']['total'] ?? 0),
            (int) $localArtist->followers
        );

        $artist['followers'] = [
            'total' => $baseFollowers + (int) $localArtist->followed_by_users_count,
        ];

        return $artist;
    }
}
