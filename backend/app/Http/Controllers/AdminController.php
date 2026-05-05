<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\SongResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\SpotifyCatalogService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly SpotifyCatalogService $spotify
    ) {
    }

    public function stats(): JsonResponse
    {
        $lastWeek = now()->subDays(6)->startOfDay();
        $activityByDay = ActivityLog::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $lastWeek)
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        return response()->json([
            'totals' => [
                'users' => User::query()->count(),
                'artists' => Artist::query()->count(),
                'songs' => Song::query()->count(),
                'playlists' => Playlist::query()->count(),
                'activityEvents' => ActivityLog::query()->count(),
            ],
            'health' => [
                'admins' => User::query()->where('role', 'admin')->count(),
                'standardUsers' => User::query()->where('role', 'user')->count(),
                'activeUsers' => User::query()->where('is_active', true)->count(),
                'inactiveUsers' => User::query()->where('is_active', false)->count(),
                'twoFactorUsers' => User::query()->where('two_factor_enabled', true)->count(),
                'newUsers7d' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'errors24h' => ActivityLog::query()
                    ->where('action', 'api.request_failed')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
            'activitySeries' => collect(range(0, 6))->map(function (int $offset) use ($lastWeek, $activityByDay): array {
                $day = $lastWeek->copy()->addDays($offset);
                $key = $day->toDateString();

                return [
                    'date' => $key,
                    'label' => $day->format('d/m'),
                    'total' => (int) ($activityByDay[$key] ?? 0),
                ];
            })->values(),
            'topArtists' => ArtistResource::collection(
                Artist::query()->withCount('followedByUsers')->orderByDesc('followed_by_users_count')->limit(5)->get()
            ),
            'topSongs' => SongResource::collection(
                Song::query()->with('album.artist')->withCount('favoritedByUsers')->orderByDesc('favorited_by_users_count')->limit(5)->get()
            ),
            'topPlaylists' => PlaylistResource::collection(
                Playlist::query()->with('user')->withCount('songs')->orderByDesc('songs_count')->limit(5)->get()
            ),
            'recentActivity' => ActivityLogResource::collection(
                ActivityLog::query()->with('user')->latest()->limit(12)->get()
            ),
            'recentErrors' => ActivityLogResource::collection(
                ActivityLog::query()
                    ->with('user')
                    ->where('action', 'api.request_failed')
                    ->latest()
                    ->limit(10)
                    ->get()
            ),
        ]);
    }

    public function users(): JsonResponse
    {
        $users = User::query()
            ->withCount(['playlists', 'favoriteSongs', 'followedArtists'])
            ->latest()
            ->get();

        return response()->json([
            'users' => UserResource::collection($users),
        ]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', Rule::in(['user', 'admin'])],
            'isActive' => ['sometimes', 'boolean'],
            'twoFactorEnabled' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['isActive'] ?? true,
            'two_factor_enabled' => $data['twoFactorEnabled'] ?? false,
        ]);

        $this->activity->record($request->user(), 'admin.user_created', 'user', $user);

        return response()->json(['user' => new UserResource($user)], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user) && $request->has('isActive') && $request->boolean('isActive') === false) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta administradora.'], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', Password::min(8)],
            'role' => ['sometimes', Rule::in(['user', 'admin'])],
            'isActive' => ['sometimes', 'boolean'],
            'twoFactorEnabled' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'role' => $data['role'] ?? $user->role,
            'is_active' => $data['isActive'] ?? $user->is_active,
            'two_factor_enabled' => $data['twoFactorEnabled'] ?? $user->two_factor_enabled,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        if (array_key_exists('twoFactorEnabled', $data) && ! $data['twoFactorEnabled']) {
            $user->twoFactorChallenges()
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);
        }

        $this->activity->record($request->user(), 'admin.user_updated', 'user', $user);

        return response()->json(['user' => new UserResource($user)]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario administrador.'], 422);
        }

        $userId = $user->id;
        $user->delete();

        $this->activity->record($request->user(), 'admin.user_deleted', 'user', $userId);

        return response()->json(['message' => 'Usuario eliminado.']);
    }

    public function activity(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->with('user')->latest();

        if ($request->filled('userId')) {
            $query->where('user_id', $request->integer('userId'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->query('action') . '%');
        }

        if ($request->query('type') === 'errors') {
            $query->where('action', 'api.request_failed');
        }

        return response()->json([
            'activity' => ActivityLogResource::collection($query->paginate(30)),
        ]);
    }

    public function userLibrary(User $user): JsonResponse
    {
        $favoriteIds = $user->favoriteSongs()
            ->whereNotNull('songs.spotify_id')
            ->orderByDesc('favorite_songs.created_at')
            ->pluck('songs.spotify_id')
            ->values()
            ->all();

        $followedIds = $user->followedArtists()
            ->whereNotNull('artists.spotify_id')
            ->orderByDesc('followed_artists.created_at')
            ->pluck('artists.spotify_id')
            ->values()
            ->all();

        $favoriteTracks = $this->orderTracksByIds($this->safeTracksByIds($favoriteIds), $favoriteIds);
        $followedArtists = $this->safeArtistsByIds($followedIds);

        if ($favoriteTracks === [] && $favoriteIds !== []) {
            $favoriteTracks = $this->getLocalTracks($favoriteIds);
        }

        if ($followedArtists === [] && $followedIds !== []) {
            $followedArtists = $this->getLocalArtists($followedIds);
        }

        return response()->json([
            'user' => new UserResource($user->loadCount(['playlists', 'favoriteSongs', 'followedArtists'])),
            'stats' => [
                'playlists' => $user->playlists()->count(),
                'favorites' => $user->favoriteSongs()->count(),
                'followedArtists' => $user->followedArtists()->count(),
            ],
            'favoriteTracks' => $favoriteTracks,
            'followedArtists' => $followedArtists,
            'playlists' => PlaylistResource::collection(
                $user->playlists()->withCount('songs')->latest()->get()
            ),
        ]);
    }

    public function userPlaylist(User $user, Playlist $playlist): JsonResponse
    {
        abort_if($playlist->user_id !== $user->id, 404);

        $playlist->loadCount('songs');

        $spotifyTrackIds = $playlist->songs()
            ->orderBy('playlist_song.position')
            ->pluck('songs.spotify_id')
            ->filter()
            ->values()
            ->all();

        $tracks = $this->orderTracksByIds($this->safeTracksByIds($spotifyTrackIds), $spotifyTrackIds);

        if ($tracks === [] && $spotifyTrackIds !== []) {
            $tracks = $this->getLocalTracks($spotifyTrackIds);
        }

        return response()->json([
            'playlist' => new PlaylistResource($playlist),
            'tracks' => $tracks,
        ]);
    }

    public function removeUserFavorite(Request $request, User $user, string $spotifyTrackId): JsonResponse
    {
        $song = Song::query()->where('spotify_id', $spotifyTrackId)->first();

        if ($song) {
            $user->favoriteSongs()->detach($song->id);
        }

        $this->activity->record($request->user(), 'admin.user_favorite_removed', 'user', $user, [
            'spotifyTrackId' => $spotifyTrackId,
        ]);

        return response()->json(['message' => 'Favorito eliminado del usuario.']);
    }

    public function removeUserFollowedArtist(Request $request, User $user, string $spotifyArtistId): JsonResponse
    {
        $artist = Artist::query()->where('spotify_id', $spotifyArtistId)->first();

        if ($artist) {
            $user->followedArtists()->detach($artist->id);
        }

        $this->activity->record($request->user(), 'admin.user_follow_removed', 'user', $user, [
            'spotifyArtistId' => $spotifyArtistId,
        ]);

        return response()->json(['message' => 'Artista eliminado de seguidos.']);
    }

    public function deleteUserPlaylist(Request $request, User $user, Playlist $playlist): JsonResponse
    {
        abort_if($playlist->user_id !== $user->id, 404);

        $playlistId = $playlist->id;
        $playlist->delete();

        $this->activity->record($request->user(), 'admin.user_playlist_deleted', 'playlist', $playlistId, [
            'userId' => $user->id,
        ]);

        return response()->json(['message' => 'Playlist eliminada del usuario.']);
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
        if ($spotifyTrackIds === []) {
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
        if ($tracks === []) {
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
        if ($spotifyArtistIds === []) {
            return [];
        }

        $artistsBySpotifyId = Artist::query()
            ->whereIn('spotify_id', $spotifyArtistIds)
            ->get()
            ->keyBy('spotify_id');

        return collect($spotifyArtistIds)
            ->map(fn (string $spotifyArtistId) => $artistsBySpotifyId->get($spotifyArtistId))
            ->filter()
            ->map(fn (Artist $artist): array => [
                'id' => $artist->spotify_id,
                'name' => $artist->name,
                'genres' => array_filter([$artist->genre]),
                'popularity' => $artist->popularity ?? 0,
                'followers' => [
                    'total' => $artist->followers ?? 0,
                ],
                'images' => $artist->image_url ? [['url' => $artist->image_url]] : [],
            ])
            ->values()
            ->all();
    }
}
