<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlaylistResource;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
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
    ) {
    }

    public function favorites(Request $request): JsonResponse
    {
        $spotifyTrackIds = Song::query()
            ->select('songs.spotify_id')
            ->join('favorite_songs', 'favorite_songs.song_id', '=', 'songs.id')
            ->where('favorite_songs.user_id', $request->user()->id)
            ->orderByDesc('favorite_songs.created_at')
            ->pluck('songs.spotify_id')
            ->filter()
            ->values()
            ->all();

        $tracks = $this->safeTracksByIds($spotifyTrackIds);

        return response()->json([
            'tracks' => $tracks,
        ]);
    }

    public function addFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        $song = $this->spotify->syncTrackBySpotifyId($data['spotifyTrackId']);

        if (! $song) {
            return response()->json(['message' => 'No se ha podido guardar la cancion.'], 422);
        }

        DB::table('favorite_songs')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'song_id' => $song->id,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->activity->record($request->user(), 'song.favorite_added', 'song', $song);

        return response()->json(['message' => 'Cancion anadida a favoritos.']);
    }

    public function removeFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        $song = Song::query()->where('spotify_id', $data['spotifyTrackId'])->first();

        if ($song) {
            DB::table('favorite_songs')
                ->where('user_id', $request->user()->id)
                ->where('song_id', $song->id)
                ->delete();
        }

        $this->activity->record($request->user(), 'song.favorite_removed', 'song', $song);

        return response()->json(['message' => 'Cancion eliminada de favoritos.']);
    }

    public function followedArtists(Request $request): JsonResponse
    {
        $spotifyArtistIds = Artist::query()
            ->select('artists.spotify_id')
            ->join('followed_artists', 'followed_artists.artist_id', '=', 'artists.id')
            ->where('followed_artists.user_id', $request->user()->id)
            ->orderByDesc('followed_artists.created_at')
            ->pluck('artists.spotify_id')
            ->filter()
            ->values()
            ->all();

        $artists = $this->safeArtistsByIds($spotifyArtistIds);

        return response()->json([
            'artists' => $artists,
        ]);
    }

    public function followArtist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyArtistId' => ['required', 'string'],
        ]);

        $artist = $this->spotify->syncArtistBySpotifyId($data['spotifyArtistId']);

        if (! $artist) {
            return response()->json(['message' => 'No se ha podido seguir al artista.'], 422);
        }

        DB::table('followed_artists')->updateOrInsert(
            [
                'user_id' => $request->user()->id,
                'artist_id' => $artist->id,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->activity->record($request->user(), 'artist.followed', 'artist', $artist);

        return response()->json(['message' => 'Artista seguido.']);
    }

    public function unfollowArtist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spotifyArtistId' => ['required', 'string'],
        ]);

        $artist = Artist::query()->where('spotify_id', $data['spotifyArtistId'])->first();

        if ($artist) {
            DB::table('followed_artists')
                ->where('user_id', $request->user()->id)
                ->where('artist_id', $artist->id)
                ->delete();
        }

        $this->activity->record($request->user(), 'artist.unfollowed', 'artist', $artist);

        return response()->json(['message' => 'Has dejado de seguir al artista.']);
    }

    public function playlists(Request $request): JsonResponse
    {
        $playlists = $request->user()
            ->playlists()
            ->withCount('songs')
            ->latest()
            ->get();

        return response()->json([
            'playlists' => PlaylistResource::collection($playlists),
        ]);
    }

    public function createPlaylist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $playlist = $request->user()->playlists()->create($data);
        $playlist->loadCount('songs');

        $this->activity->record($request->user(), 'playlist.created', 'playlist', $playlist);

        return response()->json(['playlist' => new PlaylistResource($playlist)], 201);
    }

    public function playlist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $playlist->loadCount('songs');

        $spotifyTrackIds = $playlist->songs()
            ->orderBy('playlist_song.position')
            ->pluck('songs.spotify_id')
            ->filter()
            ->values()
            ->all();

        $tracks = $this->safeTracksByIds($spotifyTrackIds);

        return response()->json([
            'playlist' => new PlaylistResource($playlist),
            'tracks' => $tracks,
        ]);
    }

    public function updatePlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ]);

        $playlist->name = $data['name'];
        $playlist->description = $data['description'] ?? null;

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('playlists', 'public');
            $playlist->cover_url = asset('storage/' . $path);
        }

        $playlist->save();
        $playlist->loadCount('songs');

        $this->activity->record($request->user(), 'playlist.updated', 'playlist', $playlist);

        return response()->json([
            'playlist' => new PlaylistResource($playlist),
        ]);
    }

    public function deletePlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $playlistId = $playlist->id;
        $playlist->delete();

        $this->activity->record($request->user(), 'playlist.deleted', 'playlist', $playlistId);

        return response()->json(['message' => 'Playlist eliminada.']);
    }

    public function addTrackToPlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        $song = $this->spotify->syncTrackBySpotifyId($data['spotifyTrackId']);

        if (! $song) {
            return response()->json(['message' => 'No se ha podido anadir la cancion.'], 422);
        }

        $nextPosition = ((int) DB::table('playlist_song')
            ->where('playlist_id', $playlist->id)
            ->max('position')) + 1;

        DB::table('playlist_song')->updateOrInsert(
            [
                'playlist_id' => $playlist->id,
                'song_id' => $song->id,
            ],
            [
                'position' => $nextPosition,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->activity->record($request->user(), 'playlist.song_added', 'playlist', $playlist, [
            'spotifyTrackId' => $data['spotifyTrackId'],
        ]);

        return response()->json(['message' => 'Cancion anadida a la playlist.']);
    }

    public function removeTrackFromPlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $data = $request->validate([
            'spotifyTrackId' => ['required', 'string'],
        ]);

        $song = Song::query()->where('spotify_id', $data['spotifyTrackId'])->first();

        if ($song) {
            DB::table('playlist_song')
                ->where('playlist_id', $playlist->id)
                ->where('song_id', $song->id)
                ->delete();
        }

        $this->activity->record($request->user(), 'playlist.song_removed', 'playlist', $playlist, [
            'spotifyTrackId' => $data['spotifyTrackId'],
        ]);

        return response()->json(['message' => 'Cancion eliminada de la playlist.']);
    }

    private function authorizePlaylist(Request $request, Playlist $playlist): void
    {
        abort_if($playlist->user_id !== $request->user()->id, 403);
    }

    private function safeTracksByIds(array $spotifyTrackIds): array
    {
        try {
            return $this->spotify->getTracksByIds($spotifyTrackIds);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function safeArtistsByIds(array $spotifyArtistIds): array
    {
        try {
            return $this->spotify->getArtistsByIds($spotifyArtistIds);
        } catch (\Throwable $exception) {
            return [];
        }
    }
}
