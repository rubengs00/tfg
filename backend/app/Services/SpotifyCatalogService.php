<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SpotifyCatalogService
{
    public function enabled(): bool
    {
        return (bool) config('services.spotify.client_id')
            && (bool) config('services.spotify.client_secret');
    }

    public function market(): string
    {
        return (string) config('services.spotify.market', 'ES');
    }

    public function token(): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Spotify no esta configurado.');
        }

        return Cache::remember('spotify_access_token', 3300, function (): string {
            $response = $this->baseHttp()
                ->asForm()
                ->withBasicAuth(
                    (string) config('services.spotify.client_id'),
                    (string) config('services.spotify.client_secret')
                )
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            $token = $response['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Spotify no ha devuelto un access token valido.');
            }

            return $token;
        });
    }

    public function search(string $query): array
    {
        if (! $this->enabled() || trim($query) === '') {
            return [
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ];
        }

        $query = trim($query);
        $cacheKey = 'spotify:search:' . md5($this->market() . '|' . $query);

        return Cache::remember($cacheKey, 900, function () use ($query): array {
            $response = $this->spotifyGet('/v1/search', [
                'q' => $query,
                'type' => 'artist,album,track',
                'limit' => 10,
                'market' => $this->market(),
            ]);

            return [
                'artists' => $response['artists']['items'] ?? [],
                'albums' => $response['albums']['items'] ?? [],
                'tracks' => $response['tracks']['items'] ?? [],
            ];
        });
    }

    public function getArtist(string $spotifyArtistId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember("spotify:artist:{$spotifyArtistId}", 3600, fn (): array => $this->spotifyGet(
            "/v1/artists/{$spotifyArtistId}",
            ['market' => $this->market()]
        ));
    }

    public function getArtistsByIds(array $spotifyArtistIds): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $spotifyArtistIds = array_values(array_unique(array_filter($spotifyArtistIds)));

        if ($spotifyArtistIds === []) {
            return [];
        }

        $artists = [];

        foreach (array_chunk($spotifyArtistIds, 50) as $chunk) {
            $cacheKey = 'spotify:artists:' . md5(implode(',', $chunk));

            $response = Cache::remember($cacheKey, 1800, fn (): array => $this->spotifyGet('/v1/artists', [
                'ids' => implode(',', $chunk),
            ]));

            foreach ($response['artists'] ?? [] as $artist) {
                if (is_array($artist)) {
                    $artists[] = $artist;
                }
            }
        }

        return $artists;
    }

    public function getArtistAlbums(string $spotifyArtistId, int $limit = 10): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $limit = max(1, min($limit, 10));
        $cacheKey = "spotify:artist:{$spotifyArtistId}:albums:{$limit}";
        $rateLimitKey = "{$cacheKey}:rate_limited";

        if ($this->getArtistAlbumsRetryAfter($spotifyArtistId, $limit) !== null) {
            return [];
        }

        return Cache::remember($cacheKey, 3600, function () use ($spotifyArtistId, $limit, $rateLimitKey): array {
            try {
                $response = $this->spotifyGet("/v1/artists/{$spotifyArtistId}/albums", [
                    'include_groups' => 'album,single',
                    'limit' => $limit,
                    'market' => $this->market(),
                ]);
            } catch (RequestException $exception) {
                if ($exception->response->status() === 429) {
                    $retryAfter = max(1, (int) ($exception->response->header('Retry-After') ?? 60));
                    $retryAt = now()->addSeconds($retryAfter);

                    Cache::put($rateLimitKey, $retryAt->timestamp, $retryAt);
                }

                throw $exception;
            }

            return $response['items'] ?? [];
        });
    }

    public function getArtistAlbumsRetryAfter(string $spotifyArtistId, int $limit = 10): ?int
    {
        $limit = max(1, min($limit, 10));
        $retryAt = Cache::get("spotify:artist:{$spotifyArtistId}:albums:{$limit}:rate_limited");

        if (! is_numeric($retryAt)) {
            return null;
        }

        $seconds = ((int) $retryAt) - now()->timestamp;

        return $seconds > 0 ? $seconds : null;
    }

    public function getAlbum(string $spotifyAlbumId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember("spotify:album:{$spotifyAlbumId}", 3600, fn (): array => $this->spotifyGet(
            "/v1/albums/{$spotifyAlbumId}",
            ['market' => $this->market()]
        ));
    }

    public function getAlbumTracks(string $spotifyAlbumId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember("spotify:album:{$spotifyAlbumId}:tracks", 3600, function () use ($spotifyAlbumId): array {
            $response = $this->spotifyGet("/v1/albums/{$spotifyAlbumId}/tracks", [
                'limit' => 50,
                'market' => $this->market(),
            ]);

            return $response['items'] ?? [];
        });
    }

    public function getTrack(string $spotifyTrackId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember("spotify:track:{$spotifyTrackId}", 3600, fn (): array => $this->spotifyGet(
            "/v1/tracks/{$spotifyTrackId}",
            ['market' => $this->market()]
        ));
    }

    public function getTracksByIds(array $spotifyTrackIds): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $spotifyTrackIds = array_values(array_unique(array_filter($spotifyTrackIds)));

        if ($spotifyTrackIds === []) {
            return [];
        }

        $tracks = [];

        foreach (array_chunk($spotifyTrackIds, 50) as $chunk) {
            $cacheKey = 'spotify:tracks:' . md5(implode(',', $chunk));

            $response = Cache::remember($cacheKey, 1800, fn (): array => $this->spotifyGet('/v1/tracks', [
                'ids' => implode(',', $chunk),
                'market' => $this->market(),
            ]));

            foreach ($response['tracks'] ?? [] as $track) {
                if (is_array($track)) {
                    $tracks[] = $track;
                }
            }
        }

        return $tracks;
    }

    public function syncSeedCatalog(?array $artistSeeds = null, int $albumsPerArtist = 3): array
    {
        if (! $this->enabled()) {
            return ['artists' => 0, 'albums' => 0, 'songs' => 0];
        }

        $artistSeeds = collect($artistSeeds ?: config('services.spotify.seed_artists', []))
            ->map(fn (mixed $seed): string => trim((string) $seed))
            ->filter()
            ->values()
            ->all();

        $importedArtists = [];
        $importedAlbums = [];
        $importedSongs = [];

        foreach ($artistSeeds as $seed) {
            $spotifyArtistId = $this->resolveArtistId($seed);

            if (! $spotifyArtistId) {
                continue;
            }

            $artist = $this->syncArtistBySpotifyId($spotifyArtistId);

            if (! $artist) {
                continue;
            }

            $importedArtists[$artist->id] = true;

            $albums = array_slice(
                $this->getArtistAlbums($spotifyArtistId, max(1, $albumsPerArtist)),
                0,
                max(1, $albumsPerArtist)
            );

            foreach ($albums as $albumSummary) {
                $spotifyAlbumId = $albumSummary['id'] ?? null;

                if (! is_string($spotifyAlbumId) || $spotifyAlbumId === '') {
                    continue;
                }

                $album = $this->syncAlbumBySpotifyId($spotifyAlbumId, $artist);

                if (! $album) {
                    continue;
                }

                $importedAlbums[$album->id] = true;

                foreach ($this->getAlbumTracks($spotifyAlbumId) as $trackData) {
                    $song = $this->upsertTrackFromSpotify($trackData, $album);

                    if ($song) {
                        $importedSongs[$song->id] = true;
                    }
                }
            }
        }

        return [
            'artists' => count($importedArtists),
            'albums' => count($importedAlbums),
            'songs' => count($importedSongs),
        ];
    }

    public function syncArtistBySpotifyId(string $spotifyArtistId): ?Artist
    {
        $artist = Artist::query()->firstOrNew(['spotify_id' => $spotifyArtistId]);

        if ($artist->exists && $artist->name) {
            return $artist;
        }

        $artistData = $this->getArtist($spotifyArtistId);

        if ($artistData !== []) {
            $artist->fill([
                'name' => $artistData['name'] ?? $artist->name ?? 'Artista',
                'slug' => $artist->slug ?: $this->buildSlug($artistData['name'] ?? 'artist', $spotifyArtistId),
                'genre' => $artistData['genres'][0] ?? null,
                'followers' => (int) ($artistData['followers']['total'] ?? 0),
                'image_url' => $artistData['images'][0]['url'] ?? null,
                'popularity' => (int) ($artistData['popularity'] ?? 0),
            ]);
        } else {
            $artist->fill([
                'name' => $artist->name ?? 'Artista',
                'slug' => $artist->slug ?: "artist_{$spotifyArtistId}",
                'genre' => $artist->genre ?? null,
                'followers' => $artist->followers ?? 0,
                'image_url' => $artist->image_url ?? null,
                'popularity' => $artist->popularity ?? 0,
            ]);
        }

        $artist->save();

        return $artist;
    }

    public function syncAlbumBySpotifyId(string $spotifyAlbumId, ?Artist $artist = null): ?Album
    {
        $existingAlbum = Album::query()->where('spotify_id', $spotifyAlbumId)->first();
        if ($existingAlbum) {
            return $existingAlbum;
        }

        $albumData = $this->getAlbum($spotifyAlbumId);

        $spotifyArtistId = $artist?->spotify_id
            ?? ($albumData['artists'][0]['id'] ?? null);

        if (! $artist && is_string($spotifyArtistId) && $spotifyArtistId !== '') {
            $artist = $this->syncArtistBySpotifyId($spotifyArtistId);
        }

        if (! $artist) {
            $artist = Artist::query()->firstOrCreate(
                ['spotify_id' => '__generic__'],
                [
                    'name' => 'Artistas varios',
                    'slug' => 'various-artists',
                    'genre' => null,
                    'followers' => 0,
                    'image_url' => null,
                    'popularity' => 0,
                ]
            );
        }

        $album = Album::query()->firstOrNew(['spotify_id' => $spotifyAlbumId]);

        if ($albumData !== []) {
            $album->fill([
                'artist_id' => $artist->id,
                'title' => $albumData['name'] ?? $album->title ?? 'Album',
                'slug' => $album->slug ?: $this->buildSlug(($artist->name ?? 'album') . ' ' . ($albumData['name'] ?? 'album'), $spotifyAlbumId),
                'cover_url' => $albumData['images'][0]['url'] ?? null,
                'release_year' => $this->releaseYear($albumData['release_date'] ?? null),
                'total_tracks' => (int) ($albumData['total_tracks'] ?? 0),
            ]);
        } else {
            $album->fill([
                'artist_id' => $artist->id,
                'title' => $album->title ?? "Album {$spotifyAlbumId}",
                'slug' => $album->slug ?: "album_{$spotifyAlbumId}",
                'cover_url' => $album->cover_url ?? null,
                'release_year' => $album->release_year ?? now()->year,
                'total_tracks' => 0,
            ]);
        }

        $album->save();

        return $album;
    }

    public function syncTrackBySpotifyId(string $spotifyTrackId): ?Song
    {
        $song = Song::query()->where('spotify_id', $spotifyTrackId)->first();

        if ($song) {
            return $song;
        }

        $trackData = $this->getTrack($spotifyTrackId);

        if ($trackData !== []) {
            return $this->upsertTrackFromSpotify($trackData);
        }

        return $this->createMinimalTrackWithAlbum($spotifyTrackId);
    }

    private function createMinimalTrackWithAlbum(string $spotifyTrackId): ?Song
    {
        $genericAlbum = Album::query()->firstOrCreate(
            ['spotify_id' => '__generic__'],
            [
                'artist_id' => 1,
                'title' => 'Canciones importadas',
                'slug' => 'imported-tracks',
                'cover_url' => null,
                'release_year' => now()->year,
                'total_tracks' => 0,
            ]
        );

        $song = Song::query()->firstOrCreate(
            ['spotify_id' => $spotifyTrackId],
            [
                'album_id' => $genericAlbum->id,
                'title' => "Cancion {$spotifyTrackId}",
                'duration_seconds' => 0,
                'preview_url' => null,
                'track_number' => 1,
                'explicit' => false,
                'popularity' => 0,
            ]
        );

        return $song;
    }

    private function upsertTrackFromSpotify(array $trackData, ?Album $album = null): ?Song
    {
        $spotifyTrackId = $trackData['id'] ?? null;

        if (! is_string($spotifyTrackId) || $spotifyTrackId === '') {
            return null;
        }

        $spotifyAlbumId = $album?->spotify_id
            ?? ($trackData['album']['id'] ?? null);

        if (! $album && is_string($spotifyAlbumId) && $spotifyAlbumId !== '') {
            $album = $this->syncAlbumBySpotifyId($spotifyAlbumId);
        }

        if (! $album) {
            return null;
        }

        $song = Song::query()->firstOrNew(['spotify_id' => $spotifyTrackId]);

        $song->fill([
            'album_id' => $album->id,
            'title' => $trackData['name'] ?? $song->title ?? 'Cancion',
            'duration_seconds' => max(0, (int) round(((int) ($trackData['duration_ms'] ?? 0)) / 1000)),
            'preview_url' => $trackData['preview_url'] ?? null,
            'track_number' => max(1, (int) ($trackData['track_number'] ?? 1)),
            'explicit' => (bool) ($trackData['explicit'] ?? false),
            'popularity' => (int) ($trackData['popularity'] ?? 0),
        ]);

        $song->save();

        return $song;
    }

    private function resolveArtistId(string $value): ?string
    {
        if ($this->looksLikeSpotifyId($value)) {
            return $value;
        }

        $response = $this->spotifyGet('/v1/search', [
            'q' => $value,
            'type' => 'artist',
            'limit' => 1,
            'market' => $this->market(),
        ]);

        $artistId = $response['artists']['items'][0]['id'] ?? null;

        return is_string($artistId) && $artistId !== '' ? $artistId : null;
    }

    private function spotifyGet(string $path, array $query = []): array
    {
        return $this->spotifyHttp()
            ->get($path, $query)
            ->throw()
            ->json();
    }

    private function spotifyHttp(): PendingRequest
    {
        return $this->baseHttp()
            ->baseUrl('https://api.spotify.com')
            ->withToken($this->token());
    }

    private function baseHttp(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders([
                'User-Agent' => 'MusicHub/1.0',
            ])
            ->withOptions($this->httpOptions());
    }

    private function httpOptions(): array
    {
        if (app()->environment('testing')) {
            return [];
        }

        $options = [
            'connect_timeout' => 12,
            'timeout' => 20,
            'force_ip_resolve' => 'v4',
        ];

        if ((bool) config('services.spotify.skip_ssl_verify', false)) {
            $options['verify'] = false;

            return $options;
        }

        $caBundle = $this->resolveCaBundle();

        if ($caBundle !== null) {
            $options['verify'] = $caBundle;
        }

        return $options;
    }

    private function resolveCaBundle(): ?string
    {
        $configured = trim((string) config('services.spotify.ca_bundle', ''));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        $iniBundle = trim((string) ini_get('openssl.cafile'));

        if ($iniBundle !== '' && is_file($iniBundle)) {
            return $iniBundle;
        }

        foreach ($this->commonWindowsCaBundles() as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function commonWindowsCaBundles(): array
    {
        return [
            'C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
            'C:\\Program Files\\Git\\usr\\ssl\\cert.pem',
            'C:\\Program Files\\Git\\mingw64\\ssl\\certs\\ca-bundle.crt',
            'C:\\Program Files\\Git\\mingw64\\ssl\\cert.pem',
        ];
    }

    private function looksLikeSpotifyId(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{22}$/', $value);
    }

    private function buildSlug(string $label, string $spotifyId): string
    {
        return Str::slug($label) . '-' . Str::lower(substr($spotifyId, -6));
    }

    private function releaseYear(mixed $releaseDate): ?int
    {
        if (! is_string($releaseDate) || strlen($releaseDate) < 4) {
            return null;
        }

        return (int) substr($releaseDate, 0, 4);
    }
}
