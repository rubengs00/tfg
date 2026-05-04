<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArtistResource;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\SongResource;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity)
    {
    }

    public function favorites(Request $request): JsonResponse
    {
        return response()->json([
            'songs' => SongResource::collection(
                $request->user()->favoriteSongs()->with('album.artist')->latest('favorite_songs.created_at')->get()
            ),
        ]);
    }

    public function addFavorite(Request $request, Song $song): JsonResponse
    {
        $request->user()->favoriteSongs()->syncWithoutDetaching([$song->id]);
        $this->activity->record($request->user(), 'song.favorite_added', 'song', $song);

        return response()->json(['message' => 'Cancion guardada en favoritos.']);
    }

    public function removeFavorite(Request $request, Song $song): JsonResponse
    {
        $request->user()->favoriteSongs()->detach($song->id);
        $this->activity->record($request->user(), 'song.favorite_removed', 'song', $song);

        return response()->json(['message' => 'Cancion eliminada de favoritos.']);
    }

    public function followedArtists(Request $request): JsonResponse
    {
        return response()->json([
            'artists' => ArtistResource::collection(
                $request->user()->followedArtists()->latest('followed_artists.created_at')->get()
            ),
        ]);
    }

    public function followArtist(Request $request, Artist $artist): JsonResponse
    {
        $request->user()->followedArtists()->syncWithoutDetaching([$artist->id]);
        $this->activity->record($request->user(), 'artist.followed', 'artist', $artist);

        return response()->json(['message' => 'Artista seguido.']);
    }

    public function unfollowArtist(Request $request, Artist $artist): JsonResponse
    {
        $request->user()->followedArtists()->detach($artist->id);
        $this->activity->record($request->user(), 'artist.unfollowed', 'artist', $artist);

        return response()->json(['message' => 'Has dejado de seguir al artista.']);
    }

    public function playlists(Request $request): JsonResponse
    {
        return response()->json([
            'playlists' => PlaylistResource::collection(
                $request->user()->playlists()->withCount('songs')->latest()->get()
            ),
        ]);
    }

    public function createPlaylist(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'isPublic' => ['sometimes', 'boolean'],
        ]);

        $playlist = $request->user()->playlists()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_public' => $data['isPublic'] ?? false,
        ]);

        $this->activity->record($request->user(), 'playlist.created', 'playlist', $playlist);

        return response()->json(['playlist' => new PlaylistResource($playlist->loadCount('songs'))], 201);
    }

    public function playlist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        return response()->json([
            'playlist' => new PlaylistResource($playlist->load('songs.album.artist')),
        ]);
    }

    public function updatePlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'isPublic' => ['sometimes', 'boolean'],
        ]);

        $playlist->fill([
            'name' => $data['name'] ?? $playlist->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $playlist->description,
            'is_public' => $data['isPublic'] ?? $playlist->is_public,
        ])->save();

        $this->activity->record($request->user(), 'playlist.updated', 'playlist', $playlist);

        return response()->json(['playlist' => new PlaylistResource($playlist->loadCount('songs'))]);
    }

    public function deletePlaylist(Request $request, Playlist $playlist): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);
        $playlistId = $playlist->id;
        $playlist->delete();

        $this->activity->record($request->user(), 'playlist.deleted', 'playlist', $playlistId);

        return response()->json(['message' => 'Playlist eliminada.']);
    }

    public function addSongToPlaylist(Request $request, Playlist $playlist, Song $song): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);
        $nextPosition = ((int) DB::table('playlist_song')->where('playlist_id', $playlist->id)->max('position')) + 1;

        $playlist->songs()->syncWithoutDetaching([
            $song->id => ['position' => $nextPosition],
        ]);

        $this->activity->record($request->user(), 'playlist.song_added', 'playlist', $playlist, [
            'song_id' => $song->id,
        ]);

        return response()->json(['playlist' => new PlaylistResource($playlist->load('songs.album.artist'))]);
    }

    public function removeSongFromPlaylist(Request $request, Playlist $playlist, Song $song): JsonResponse
    {
        $this->authorizePlaylist($request, $playlist);
        $playlist->songs()->detach($song->id);

        $this->activity->record($request->user(), 'playlist.song_removed', 'playlist', $playlist, [
            'song_id' => $song->id,
        ]);

        return response()->json(['playlist' => new PlaylistResource($playlist->load('songs.album.artist'))]);
    }

    private function authorizePlaylist(Request $request, Playlist $playlist): void
    {
        abort_if($playlist->user_id !== $request->user()->id, 403, 'No puedes modificar esta playlist.');
    }
}
