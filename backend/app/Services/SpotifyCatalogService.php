<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SpotifyCatalogService
{
    public function enabled(): bool
    {
        return (bool) config('services.spotify.client_id') && (bool) config('services.spotify.client_secret');
    }

    public function market(): string
    {
        return (string) config('services.spotify.market', 'ES');
    }

    public function defaultSeedArtists(): array
    {
        return array_values(array_filter(config('services.spotify.seed_artists', [])));
    }

    public function search(string $query): ?array
    {
        if (! $this->enabled() || mb_strlen($query) < 2) {
            return null;
        }

        try {
            $response = $this->spotifyGet('/v1/search', [
                'q' => $query,
                'type' => 'artist,album,track',
                'limit' => 8,
                'market' => $this->market(),
            ]);
        } catch (Throwable) {
            return null;
        }

        return [
            'source' => 'spotify',
            'artists' => $this->orderedArtists(
                collect($response['artists']['items'] ?? [])
                    ->map(fn (array $artist): int => $this->upsertArtist($artist)->id)
                    ->all()
            ),
            'albums' => $this->orderedAlbums(
                collect($response['albums']['items'] ?? [])
                    ->map(fn (array $album): int => $this->upsertAlbumFromSpotify($album)->id)
                    ->all()
            ),
            'songs' => $this->orderedSongs(
                collect($response['tracks']['items'] ?? [])
                    ->map(fn (array $track): int => $this->upsertSongFromSpotify($track)->id)
                    ->all()
            ),
        ];
    }

    public function syncSeedCatalog(?array $artistQueries = null, int $albumsPerArtist = 3): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Spotify credentials are not configured.');
        }

        $artists = collect($artistQueries ?? $this->defaultSeedArtists())
            ->map(fn (mixed $artist): string => trim((string) $artist))
            ->filter()
            ->values();

        $syncedArtistIds = [];
        $syncedAlbumIds = [];
        $syncedSongIds = [];

        foreach ($artists as $artistQuery) {
            $spotifyArtistId = $this->resolveArtistId($artistQuery);

            if ($spotifyArtistId === null) {
                continue;
            }

            $synced = $this->syncArtistCatalog($spotifyArtistId, $albumsPerArtist);

            if ($synced['artist'] instanceof Artist) {
                $syncedArtistIds[] = $synced['artist']->id;
            }

            $syncedAlbumIds = [...$syncedAlbumIds, ...$synced['album_ids']];
            $syncedSongIds = [...$syncedSongIds, ...$synced['song_ids']];
        }

        return [
            'artists' => count(array_unique($syncedArtistIds)),
            'albums' => count(array_unique($syncedAlbumIds)),
            'songs' => count(array_unique($syncedSongIds)),
        ];
    }

    private function syncArtistCatalog(string $spotifyArtistId, int $albumsPerArtist): array
    {
        $artistData = $this->spotifyGet("/v1/artists/{$spotifyArtistId}");
        $artist = $this->upsertArtist($artistData);

        $albumsResponse = $this->spotifyGet("/v1/artists/{$spotifyArtistId}/albums", [
            'include_groups' => 'album,single',
            'limit' => min(max($albumsPerArtist, 1), 10),
            'market' => $this->market(),
        ]);

        $albumIds = [];
        $songIds = [];

        collect($albumsResponse['items'] ?? [])
            ->filter(fn (mixed $album): bool => is_array($album) && filled($album['id'] ?? null))
            ->unique('id')
            ->take($albumsPerArtist)
            ->each(function (array $albumSummary) use (&$albumIds, &$songIds, $artist): void {
                $albumData = $this->spotifyGet("/v1/albums/{$albumSummary['id']}", [
                    'market' => $this->market(),
                ]);

                $album = $this->upsertAlbumFromSpotify($albumData, $artist);
                $albumIds[] = $album->id;

                foreach ($this->fetchTracksForAlbum($albumData) as $track) {
                    $songIds[] = $this->upsertSongFromSpotify($track, $album)->id;
                }
            });

        return [
            'artist' => $artist,
            'album_ids' => $albumIds,
            'song_ids' => $songIds,
        ];
    }

    private function resolveArtistId(string $artistQuery): ?string
    {
        if (preg_match('/^[A-Za-z0-9]{22}$/', $artistQuery) === 1) {
            return $artistQuery;
        }

        $response = $this->spotifyGet('/v1/search', [
            'q' => $artistQuery,
            'type' => 'artist',
            'limit' => 1,
            'market' => $this->market(),
        ]);

        return $response['artists']['items'][0]['id'] ?? null;
    }

    private function fetchTracksForAlbum(array $albumData): array
    {
        $simplifiedTracks = collect($albumData['tracks']['items'] ?? [])
            ->filter(fn (mixed $track): bool => is_array($track) && filled($track['id'] ?? null))
            ->values();

        $trackIds = $simplifiedTracks
            ->pluck('id')
            ->filter()
            ->values();

        if ($trackIds->isEmpty()) {
            return [];
        }

        try {
            return $trackIds
                ->chunk(50)
                ->flatMap(function ($chunk): array {
                    $response = $this->spotifyGet('/v1/tracks', [
                        'ids' => implode(',', $chunk->all()),
                        'market' => $this->market(),
                    ]);

                    return $response['tracks'] ?? [];
                })
                ->filter(fn (mixed $track): bool => is_array($track) && filled($track['id'] ?? null))
                ->values()
                ->all();
        } catch (Throwable) {
            // Some catalogs return 403 for /v1/tracks even though the album payload contains
            // usable simplified track objects. Falling back keeps the local catalog importable.
            return $simplifiedTracks->all();
        }
    }

    private function spotifyGet(string $path, array $query = []): array
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->get('https://api.spotify.com'.$path, $query)
            ->throw()
            ->json();
    }

    private function accessToken(): string
    {
        return Cache::remember('spotify_access_token', 3300, function (): string {
            $response = Http::asForm()
                ->withBasicAuth(config('services.spotify.client_id'), config('services.spotify.client_secret'))
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            return $response['access_token'];
        });
    }

    private function upsertArtist(array $artistData): Artist
    {
        $spotifyId = (string) ($artistData['id'] ?? '');

        if ($spotifyId === '') {
            throw new RuntimeException('Spotify artist payload is missing its id.');
        }

        $existing = Artist::query()->where('spotify_id', $spotifyId)->first();

        return Artist::query()->updateOrCreate(
            ['spotify_id' => $spotifyId],
            [
                'name' => (string) ($artistData['name'] ?? $existing?->name ?? 'Artista'),
                'slug' => $this->spotifySlug((string) ($artistData['name'] ?? $existing?->name ?? 'artist'), $spotifyId),
                'genre' => $artistData['genres'][0] ?? $existing?->genre,
                'followers' => (int) ($artistData['followers']['total'] ?? $existing?->followers ?? 0),
                'image_url' => $artistData['images'][0]['url'] ?? $existing?->image_url,
                'bio' => $existing?->bio,
                'popularity' => (int) ($artistData['popularity'] ?? $existing?->popularity ?? 0),
            ],
        );
    }

    private function upsertAlbumFromSpotify(array $albumData, ?Artist $fallbackArtist = null): Album
    {
        $spotifyId = (string) ($albumData['id'] ?? '');

        if ($spotifyId === '') {
            throw new RuntimeException('Spotify album payload is missing its id.');
        }

        $artist = $this->resolveAlbumArtist($albumData, $fallbackArtist);
        $existing = Album::query()->where('spotify_id', $spotifyId)->first();
        $title = (string) ($albumData['name'] ?? $existing?->title ?? 'Album');

        return Album::query()->updateOrCreate(
            ['spotify_id' => $spotifyId],
            [
                'artist_id' => $artist->id,
                'title' => $title,
                'slug' => $this->spotifySlug($title, $spotifyId),
                'cover_url' => $albumData['images'][0]['url'] ?? $existing?->cover_url,
                'release_year' => $this->extractReleaseYear($albumData['release_date'] ?? null) ?? $existing?->release_year,
                'total_tracks' => (int) ($albumData['total_tracks'] ?? $existing?->total_tracks ?? 0),
            ],
        );
    }

    private function upsertSongFromSpotify(array $trackData, ?Album $album = null): Song
    {
        $spotifyId = (string) ($trackData['id'] ?? '');

        if ($spotifyId === '') {
            throw new RuntimeException('Spotify track payload is missing its id.');
        }

        $album ??= $this->resolveSongAlbum($trackData);
        $existing = Song::query()->where('spotify_id', $spotifyId)->first();

        return Song::query()->updateOrCreate(
            ['spotify_id' => $spotifyId],
            [
                'album_id' => $album->id,
                'title' => (string) ($trackData['name'] ?? $existing?->title ?? 'Cancion'),
                'duration_seconds' => (int) round(($trackData['duration_ms'] ?? ($existing?->duration_seconds * 1000) ?? 0) / 1000),
                'preview_url' => $trackData['preview_url'] ?? $existing?->preview_url,
                'track_number' => (int) ($trackData['track_number'] ?? $existing?->track_number ?? 1),
                'explicit' => (bool) ($trackData['explicit'] ?? $existing?->explicit ?? false),
                'popularity' => (int) ($trackData['popularity'] ?? $existing?->popularity ?? 0),
            ],
        );
    }

    private function resolveAlbumArtist(array $albumData, ?Artist $fallbackArtist = null): Artist
    {
        if ($fallbackArtist instanceof Artist) {
            $albumArtistIds = collect($albumData['artists'] ?? [])
                ->map(fn (mixed $artist): ?string => is_array($artist) ? ($artist['id'] ?? null) : null)
                ->filter()
                ->values();

            if ($albumArtistIds->contains($fallbackArtist->spotify_id)) {
                return $fallbackArtist;
            }
        }

        $artistPayload = $albumData['artists'][0] ?? null;

        if (is_array($artistPayload) && filled($artistPayload['id'] ?? null)) {
            return $this->upsertArtist($artistPayload);
        }

        if ($fallbackArtist instanceof Artist) {
            return $fallbackArtist;
        }

        throw new RuntimeException('Spotify album payload is missing its primary artist.');
    }

    private function resolveSongAlbum(array $trackData): Album
    {
        $albumPayload = $trackData['album'] ?? null;

        if (! is_array($albumPayload)) {
            throw new RuntimeException('Spotify track payload is missing its album.');
        }

        return $this->upsertAlbumFromSpotify($albumPayload);
    }

    private function extractReleaseYear(mixed $releaseDate): ?int
    {
        $year = (int) mb_substr((string) $releaseDate, 0, 4);

        return $year > 0 ? $year : null;
    }

    private function spotifySlug(string $value, string $spotifyId): string
    {
        return Str::slug($value).'-'.$spotifyId;
    }

    private function orderedArtists(array $ids): EloquentCollection
    {
        return $this->orderedModels(
            Artist::query()->whereKey(array_values(array_unique($ids)))->get(),
            $ids,
        );
    }

    private function orderedAlbums(array $ids): EloquentCollection
    {
        return $this->orderedModels(
            Album::query()->with('artist')->whereKey(array_values(array_unique($ids)))->get(),
            $ids,
        );
    }

    private function orderedSongs(array $ids): EloquentCollection
    {
        return $this->orderedModels(
            Song::query()->with('album.artist')->whereKey(array_values(array_unique($ids)))->get(),
            $ids,
        );
    }

    private function orderedModels(EloquentCollection $models, array $ids): EloquentCollection
    {
        $lookup = $models->keyBy('id');

        return new EloquentCollection(
            collect($ids)
                ->unique()
                ->map(fn (int $id) => $lookup->get($id))
                ->filter()
                ->all()
        );
    }
}
