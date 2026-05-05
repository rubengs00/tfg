<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;

use App\Models\Artist as LocalArtist;
use App\Models\Album as LocalAlbum;
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

        $albums = $this->popularArtistAlbums($results['artists'] ?? [], $results['albums'] ?? []);

        return response()->json([
            'source' => 'spotify',
            'genre' => $genre,
            'artists' => $results['artists'],
            'albums' => $albums,
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
            $album = [];
            $tracks = [];
        }

        if ($album === []) {
            $localAlbum = $this->localAlbum($spotifyId);

            if ($localAlbum !== null) {
                return response()->json($localAlbum);
            }
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

    private function randomizeAlbums(array $albums): array
    {
        $uniqueAlbums = collect($albums)
            ->filter(fn (mixed $album): bool => is_array($album) && ! empty($album['id']))
            ->unique('id')
            ->values()
            ->all();

        shuffle($uniqueAlbums);

        return array_slice($uniqueAlbums, 0, 10);
    }

    private function popularArtistAlbums(array $artists, array $fallbackAlbums): array
    {
        $albums = [];

        foreach (array_slice($artists, 0, 6) as $artist) {
            $artistId = $artist['id'] ?? null;

            if (! is_string($artistId) || $artistId === '') {
                continue;
            }

            try {
                $albums = [
                    ...$albums,
                    ...$this->spotify->getArtistAlbums($artistId, 3),
                ];
            } catch (\Throwable $exception) {
                continue;
            }
        }

        $albums = $this->randomizeAlbums($albums);

        if (! empty($albums)) {
            return $albums;
        }

        $albums = $this->randomizeAlbums($fallbackAlbums);

        return $albums ?: $this->localRandomAlbums();
    }

    private function localRandomAlbums(): array
    {
        return LocalAlbum::query()
            ->with('artist')
            ->whereNotNull('spotify_id')
            ->inRandomOrder()
            ->limit(10)
            ->get()
            ->map(fn (LocalAlbum $album): array => $this->localAlbumPayload($album))
            ->values()
            ->all();
    }

    private function localAlbum(string $spotifyId): ?array
    {
        $album = LocalAlbum::query()
            ->with(['artist', 'songs.album.artist'])
            ->where('spotify_id', $spotifyId)
            ->first();

        if (! $album) {
            return null;
        }

        return [
            'album' => $this->localAlbumPayload($album),
            'tracks' => $album->songs
                ->map(fn ($song): array => [
                    'id' => $song->spotify_id,
                    'name' => $song->title,
                    'duration_ms' => $song->duration_seconds * 1000,
                    'preview_url' => $song->preview_url,
                    'explicit' => $song->explicit,
                    'popularity' => $song->popularity,
                    'track_number' => $song->track_number,
                    'album' => $this->localAlbumPayload($album),
                    'artists' => $album->artist ? [[
                        'id' => $album->artist->spotify_id,
                        'name' => $album->artist->name,
                    ]] : [],
                ])
                ->values()
                ->all(),
        ];
    }

    private function localAlbumPayload(LocalAlbum $album): array
    {
        return [
            'id' => $album->spotify_id,
            'name' => $album->title,
            'release_date' => $album->release_year ? (string) $album->release_year : null,
            'total_tracks' => $album->total_tracks,
            'images' => $album->cover_url ? [['url' => $album->cover_url]] : [],
            'artists' => $album->artist ? [[
                'id' => $album->artist->spotify_id,
                'name' => $album->artist->name,
            ]] : [],
        ];
    }
}
