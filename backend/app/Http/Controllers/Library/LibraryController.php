<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;

use App\Http\Resources\PlaylistResource;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\SpotifyCatalogService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LibraryController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify,
        private readonly ActivityLogService $activity
    ) {
    }

    public function favorites(Request $request): JsonResponse
    {
        $spotifyTrackIds = Song::query()
            ->select('songs.spotify_id')
            ->join('favorite_songs', 'favorite_songs.song_id', '=', 'songs.id')
            ->where('favorite_songs.user_id', $request->user()->id)
            ->whereNotNull('songs.spotify_id')
            ->orderByDesc('favorite_songs.created_at')
            ->pluck('songs.spotify_id')
            ->filter()
            ->values()
            ->all();

        $tracks = $this->orderTracksByIds($this->safeTracksByIds($spotifyTrackIds), $spotifyTrackIds);

        if (empty($tracks) && ! empty($spotifyTrackIds)) {
            $tracks = $this->getLocalTracks($spotifyTrackIds);
        }

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
            ->whereNotNull('artists.spotify_id')
            ->orderByDesc('followed_artists.created_at')
            ->pluck('artists.spotify_id')
            ->filter()
            ->values()
            ->all();

        $artists = $this->safeArtistsByIds($spotifyArtistIds);

        if (empty($artists) && ! empty($spotifyArtistIds)) {
            $artists = $this->getLocalArtists($spotifyArtistIds);
        }

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

        $tracks = $this->orderTracksByIds($this->safeTracksByIds($spotifyTrackIds), $spotifyTrackIds);

        if (empty($tracks) && ! empty($spotifyTrackIds)) {
            $tracks = $this->getLocalTracks($spotifyTrackIds);
        }

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
            'cover' => ['nullable', 'file', 'max:4096'],
        ]);

        $playlist->name = $data['name'];
        $playlist->description = $data['description'] ?? null;

        if ($request->hasFile('cover')) {
            $cover = $request->file('cover');
            $extension = $this->validatePlaylistCover($cover);
            $filename = 'playlist-'.$playlist->id.'-'.Str::uuid().'.'.$extension;
            $path = $this->storePlaylistCover($cover, $filename);
            $playlist->cover_url = $this->publicStorageUrl($path);
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

        $playlist->loadCount('songs');

        return response()->json([
            'message' => 'Cancion anadida a la playlist.',
            'playlist' => new PlaylistResource($playlist),
        ]);
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

        $playlist->loadCount('songs');

        return response()->json([
            'message' => 'Cancion eliminada de la playlist.',
            'playlist' => new PlaylistResource($playlist),
        ]);
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

    private function getLocalTracks(array $spotifyTrackIds): array
    {
        if (empty($spotifyTrackIds)) {
            return [];
        }

        $songsBySpotifyId = Song::query()
            ->whereIn('spotify_id', $spotifyTrackIds)
            ->with('album.artist')
            ->get()
            ->keyBy('spotify_id');

        return collect($spotifyTrackIds)
            ->map(fn (string $spotifyTrackId) => $songsBySpotifyId->get($spotifyTrackId))
            ->filter()
            ->map(fn (Song $song): array => $this->localTrackPayload($song))
            ->values()
            ->all();
    }

    private function localTrackPayload(Song $song): array
    {
        return [
            'id' => $song->spotify_id,
            'name' => $song->title ?? 'Cancion',
            'duration_ms' => ($song->duration_seconds ?? 0) * 1000,
            'preview_url' => $song->preview_url,
            'explicit' => $song->explicit ?? false,
            'popularity' => $song->popularity ?? 0,
            'track_number' => $song->track_number ?? 1,
            'album' => [
                'id' => $song->album?->spotify_id ?? '',
                'name' => $song->album?->title ?? 'Album',
                'images' => $song->album?->cover_url ? [['url' => $song->album->cover_url]] : [],
                'artists' => $song->album?->artist ? [[
                    'id' => $song->album->artist->spotify_id,
                    'name' => $song->album->artist->name,
                ]] : [],
            ],
            'artists' => $song->album?->artist ? [[
                'id' => $song->album->artist->spotify_id,
                'name' => $song->album->artist->name,
            ]] : [],
        ];
    }

    private function orderTracksByIds(array $tracks, array $spotifyTrackIds): array
    {
        if (empty($tracks)) {
            return [];
        }

        $tracksById = collect($tracks)
            ->filter(fn (mixed $track): bool => is_array($track) && isset($track['id']))
            ->keyBy('id');

        return collect($spotifyTrackIds)
            ->map(fn (string $spotifyTrackId) => $tracksById->get($spotifyTrackId))
            ->filter()
            ->values()
            ->all();
    }

    private function getLocalArtists(array $spotifyArtistIds): array
    {
        if (empty($spotifyArtistIds)) {
            return [];
        }

        $artists = Artist::query()
            ->whereIn('spotify_id', $spotifyArtistIds)
            ->get();

        return $artists->map(function (Artist $artist) {
            return [
                'id' => $artist->spotify_id,
                'name' => $artist->name,
                'genres' => array_filter([$artist->genre]),
                'popularity' => $artist->popularity ?? 0,
                'followers' => [
                    'total' => $artist->followers ?? 0,
                ],
                'images' => $artist->image_url ? [['url' => $artist->image_url]] : [],
            ];
        })->values()->all();
    }

    private function validatePlaylistCover(UploadedFile $cover): string
    {
        $imageInfo = @getimagesize($cover->getRealPath());
        $imageType = $imageInfo[2] ?? null;
        $extensions = [
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (! isset($extensions[$imageType])) {
            throw ValidationException::withMessages([
                'cover' => ['La portada debe ser una imagen JPG, PNG, GIF o WebP.'],
            ]);
        }

        return $extensions[$imageType];
    }

    private function storePlaylistCover(UploadedFile $cover, string $filename): string
    {
        $root = rtrim(
            (string) config('filesystems.disks.public.root', storage_path('app/public')),
            DIRECTORY_SEPARATOR
        );
        $directory = $root.DIRECTORY_SEPARATOR.'playlists';

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ValidationException::withMessages([
                'cover' => ['No se pudo preparar el directorio de portadas.'],
            ]);
        }

        $cover->move($directory, $filename);

        return 'playlists/'.$filename;
    }

    private function publicStorageUrl(string $path): string
    {
        return rtrim(
            (string) config('filesystems.disks.public.url', url('/storage')),
            '/'
        ).'/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
