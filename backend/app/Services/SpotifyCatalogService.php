<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpotifyCatalogService
{
    private function fallbackCurlGetJson(string $url, array $query = []): array
    {
        $token = $this->token();

        $qs = $query ? ('?' . http_build_query($query)) : '';
        $fullUrl = $url . $qs;

        $curl = (string) config('services.spotify.curl_path', 'curl');
        // Usamos curl.exe del sistema como workaround cuando PHP cURL/Guzzle falla en Windows.
        // -s: silent, -L: follow redirects, -H: headers
        $cmd = '"' . $curl . '" -s -L '
            . '-H "Accept: application/json" '
            . '-H "Authorization: Bearer ' . $token . '" '
            . '"' . $fullUrl . '"';

        $out = @shell_exec($cmd);
        if (!is_string($out) || trim($out) === '') {
            return [];
        }

        $decoded = json_decode($out, true);
        return is_array($decoded) ? $decoded : [];
    }
    /* =====================================================
     |  CORE CONFIG
     ===================================================== */

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
        return Cache::remember('spotify_access_token', 3300, function (): string {
            $response = Http::asForm()
                ->withBasicAuth(
                    config('services.spotify.client_id'),
                    config('services.spotify.client_secret')
                )
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            return $response['access_token'];
        });
    }

    private function http()
    {
        $http = Http::withToken($this->token())
            ->acceptJson()
            ->withOptions([
                // Windows/Laragon a veces falla resolviendo IPv6 y lanza ConnectionException.
                // Forzamos IPv4 para Spotify.
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);

        // En algunos entornos Windows/Laragon, PHP cURL/OpenSSL no tiene CA bundle configurado
        // y las llamadas HTTPS a Spotify fallan con ConnectionException.
        // Permite saltar la verificación SOLO si se define en .env.
        if (config('services.spotify.skip_ssl_verify', false)) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }

    /* =====================================================
     |  SEARCH
     ===================================================== */

    public function search(string $query): array
    {
        if (! $this->enabled() || trim($query) === '') {
            return [
                'artists' => [],
                'albums' => [],
                'tracks' => [],
            ];
        }

        $paramsArtist = [
            'q' => $query,
            'type' => 'artist',
            'limit' => 10,
            'market' => $this->market(),
        ];

        $paramsAlbum = [
            'q' => $query,
            'type' => 'album',
            'limit' => 10,
            'market' => $this->market(),
        ];

        $paramsTrack = [
            'q' => $query,
            'type' => 'track',
            'limit' => 10,
            'market' => $this->market(),
        ];

        try {
            $http = $this->http();

            $artistResponse = $http->get('https://api.spotify.com/v1/search', $paramsArtist)->throw()->json();
            $albumResponse = $http->get('https://api.spotify.com/v1/search', $paramsAlbum)->throw()->json();
            $trackResponse = $http->get('https://api.spotify.com/v1/search', $paramsTrack)->throw()->json();
        } catch (ConnectionException $e) {
            // Workaround Windows/Laragon: usar curl.exe si Guzzle no conecta
            $artistResponse = $this->fallbackCurlGetJson('https://api.spotify.com/v1/search', $paramsArtist);
            $albumResponse = $this->fallbackCurlGetJson('https://api.spotify.com/v1/search', $paramsAlbum);
            $trackResponse = $this->fallbackCurlGetJson('https://api.spotify.com/v1/search', $paramsTrack);
        }

        return [
            'artists' => $artistResponse['artists']['items'] ?? [],
            'albums' => $albumResponse['albums']['items'] ?? [],
            'tracks' => $trackResponse['tracks']['items'] ?? [],
        ];
    }

    /* =====================================================
     |  ARTISTS
     ===================================================== */

    public function getArtist(string $id): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember("spotify:artist:{$id}", 3600, function () use ($id) {
            try {
                return $this->http()
                    ->get("https://api.spotify.com/v1/artists/{$id}", [
                        'market' => $this->market(),
                    ])
                    ->throw()
                    ->json();
            } catch (ConnectionException $e) {
                return $this->fallbackCurlGetJson("https://api.spotify.com/v1/artists/{$id}", [
                    'market' => $this->market(),
                ]);
            }
        });
    }

    public function getArtistsByIds(array $ids): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) {
            return [];
        }

        $cacheKey = 'spotify:artists:' . md5(implode(',', $ids));

        return Cache::remember($cacheKey, 1800, function () use ($ids) {
            $response = $this->http()
                ->get('https://api.spotify.com/v1/artists', [
                    'ids' => implode(',', $ids),
                ])
                ->throw()
                ->json();

            return $response['artists'] ?? [];
        });
    }

    public function getArtistAlbums(string $id): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Cache::remember("spotify:artist:{$id}:albums", 3600, function () use ($id) {
            try {
                $response = $this->http()
                    ->get("https://api.spotify.com/v1/artists/{$id}/albums", [
                        'include_groups' => 'album,single',
                        'limit' => 50,
                        'market' => $this->market(),
                    ])
                    ->throw()
                    ->json();
            } catch (ConnectionException $e) {
                $response = $this->fallbackCurlGetJson("https://api.spotify.com/v1/artists/{$id}/albums", [
                    'include_groups' => 'album,single',
                    'limit' => 50,
                    'market' => $this->market(),
                ]);
            }

            return $response['items'] ?? [];
        });
    }

    /* =====================================================
     |  ALBUMS
     ===================================================== */

    public function getAlbum(string $id): array
    {
        return Cache::remember("spotify:album:{$id}", 3600, function () use ($id) {
            return $this->http()
                ->get("https://api.spotify.com/v1/albums/{$id}")
                ->throw()
                ->json();
        });
    }

    public function getAlbumTracks(string $id): array
    {
        return Cache::remember("spotify:album:{$id}:tracks", 3600, function () use ($id) {
            $response = $this->http()
                ->get("https://api.spotify.com/v1/albums/{$id}/tracks", [
                    'limit' => 50,
                    'market' => $this->market(),
                ])
                ->throw()
                ->json();

            return $response['items'] ?? [];
        });
    }

    /* =====================================================
     |  TRACKS
     ===================================================== */

    public function getTracksByIds(array $ids): array
    {
        $ids = array_unique($ids);
        if (empty($ids)) return [];

        $cacheKey = 'spotify:tracks:' . md5(implode(',', $ids));

        return Cache::remember($cacheKey, 1800, function () use ($ids) {
            $response = $this->http()
                ->get('https://api.spotify.com/v1/tracks', [
                    'ids' => implode(',', $ids),
                    'market' => $this->market(),
                ])
                ->throw()
                ->json();

            return $response['tracks'] ?? [];
        });
    }
}
