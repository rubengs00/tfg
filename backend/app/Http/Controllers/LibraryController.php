<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use App\Services\SpotifyCatalogService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify,
        private readonly ActivityLogger $activity
    ) {}

    /* =====================================================
     |  FAVORITES (Spotify IDs only)
     ===================================================== */

    public function favorites(Request $request): JsonResponse
    {
        // Spotify-first: persistimos IDs de Spotify en la tabla favorite_songs (columna spotify_track_id)
        $ids = DB::table('favorite_songs')
            ->where('user_id', $request->user()->id)
            ->pluck('spotify_track_id')
            ->toArray();

        $tracks = $this->spotify->getTracksByIds($ids);

        return response()->json([
            'tracks' => $tracks,
        ]);
    }

    public function addFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        DB::table('favorite_songs')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'spotify_track_id' => $data['spotifyTrackId'],
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->activity->record($request->user(), 'song.favorite_added');

        return response()->json(['message' => 'Canción añadida a favoritos.']);
    }

    public function removeFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        DB::table('favorite_songs')
            ->where('user_id', $request->user()->id)
            ->where('spotify_track_id', $data['spotifyTrackId'])
            ->delete();

        $this->activity->record($request->user(), 'song.favorite_removed');

        return response()->json(['message' => 'Canción eliminada de favoritos.']);
    }

    /* =====================================================
     |  FOLLOWED ARTISTS
     ===================================================== */

    public function followedArtists(Request $request): JsonResponse
    {
        $ids = DB::table('followed_artists')
            ->where('user_id', $request->user()->id)
            ->pluck('spotify_artist_id')
            ->toArray();

        $artists = $this->spotify->getArtistsByIds($ids);

        return response()->json([
            'artists' => $artists,
        ]);
    }

    public function followArtist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyArtistId' => ['required', 'string'],
        ]);

        DB::table('followed_artists')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'spotify_artist_id' => $data['spotifyArtistId'],
            ],
            [
                'created_at' => now(),
            ]
        );

        $this->activity->record($request->user(), 'artist.followed');

        return response()->json(['message' => 'Artista seguido.']);
    }

    public function unfollowArtist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyArtistId' => ['required', 'string'],
        ]);

        DB::table('followed_artists')
            ->where('user_id', $request->user()->id)
            ->where('spotify_artist_id', $data['spotifyArtistId'])
            ->delete();

        $this->activity->record($request->user(), 'artist.unfollowed');

        return response()->json(['message' => 'Has dejado de seguir al artista.']);
    }

    /* =====================================================
     |  PLAYLISTS
     ===================================================== */

    public function playlists(Request $request): JsonResponse
    {
        return response()->json([
            'playlists' => $request->user()->playlists()->latest()->get(),
        ]);
    }

    public function createPlaylist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $playlist = $request->user()->playlists()->create($data);

        $this->activity->record($request->user(), 'playlist.created');

        return response()->json(['playlist' => $playlist], 201);
    }

    public function playlist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $ids = DB::table('playlist_tracks')
            ->where('playlist_id', $playlist->id)
            ->orderBy('position')
            ->pluck('spotify_track_id')
            ->toArray();

        $tracks = $this->spotify->getTracksByIds($ids);

        return response()->json([
            'playlist' => $playlist,
            'tracks' => $tracks,
        ]);
    }

    public function addTrackToPlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        $nextPosition = ((int) DB::table('playlist_tracks')
            ->where('playlist_id', $playlist->id)
            ->max('position')) + 1;

        DB::table('playlist_tracks')->insert([
            'playlist_id' => $playlist->id,
            'spotify_track_id' => $data['spotifyTrackId'],
            'position' => $nextPosition,
            'created_at' => now(),
        ]);

        $this->activity->record($request->user(), 'playlist.song_added');

        return response()->json(['message' => 'Canción añadida a la playlist.']);
    }

    public function removeTrackFromPlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        DB::table('playlist_tracks')
            ->where('playlist_id', $playlist->id)
            ->where('spotify_track_id', $data['spotifyTrackId'])
            ->delete();

        $this->activity->record($request->user(), 'playlist.song_removed');

        return response()->json(['message' => 'Canción eliminada de la playlist.']);
    }

    private function authorizePlaylist(Request $request, Playlist $playlist): void
    {
        abort_if($playlist->user_id !== $request->user()->id, 403);
    }
}
