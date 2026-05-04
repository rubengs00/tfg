<?php

namespace App\Http\Controllers;

use App\Services\SpotifyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify
    ) {}

    /* =====================================================
     |  HOME (Discover dinámico Spotify)
     ===================================================== */

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
        } catch (\Throwable $e) {
            // Si Spotify falla/no está configurado no debe petar el frontend.
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

    /* =====================================================
     |  SEARCH
     ===================================================== */

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        try {
            $results = $this->spotify->search($query);
        } catch (\Throwable $e) {
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

    /* =====================================================
     |  ARTIST
     ===================================================== */

    public function artist(string $id): JsonResponse
    {
        $artist = null;
        $albums = [];

        try {
            $artistData = $this->spotify->getArtist($id);
            if (!empty($artistData)) {
                $artist = $artistData;
            }
        } catch (\Throwable $e) {
            // No rompemos toda la respuesta: devolvemos artist null + diagnóstico
            $status = method_exists($e, 'getCode') ? (int) $e->getCode() : null;

            return response()->json([
                'artist' => null,
                'albums' => [],
                'message' => 'Spotify no disponible para cargar artista en este entorno.',
                'spotifyEnabled' => $this->spotify->enabled(),
                'spotifySkipSslVerify' => (bool) config('services.spotify.skip_ssl_verify', false),
                'spotifyError' => [
                    'type' => get_class($e),
                    'status' => $status ?: null,
                ],
            ], 200);
        }

        // Albums: si falla, solo degradamos albums, pero mantenemos el artista si lo tenemos
        try {
            $albums = $this->spotify->getArtistAlbums($id) ?: [];
        } catch (\Throwable $e) {
            $albums = [];
        }

        // Si no tenemos artista, devolvemos mensaje estable
        if ($artist === null) {
            return response()->json([
                'artist' => null,
                'albums' => [],
                'message' => 'Spotify no disponible para cargar artista en este entorno.',
                'spotifyEnabled' => $this->spotify->enabled(),
                'spotifySkipSslVerify' => (bool) config('services.spotify.skip_ssl_verify', false),
            ], 200);
        }

        return response()->json([
            'artist' => $artist,
            'albums' => $albums,
        ]);
    }

    /* =====================================================
     |  ALBUM
     ===================================================== */

    public function album(string $id): JsonResponse
    {
        try {
            $album = $this->spotify->getAlbum($id);
            $tracks = $this->spotify->getAlbumTracks($id);
        } catch (\Throwable $e) {
            return response()->json([
                'album' => null,
                'tracks' => [],
                'message' => 'Spotify no disponible para cargar álbum en este entorno.',
            ], 200);
        }

        return response()->json([
            'album' => $album,
            'tracks' => $tracks,
        ]);
    }
}
