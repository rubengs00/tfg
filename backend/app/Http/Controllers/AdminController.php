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
        return response()->json([
            'totals' => [
                'users' => User::query()->count(),
                'artists' => Artist::query()->count(),
                'songs' => Song::query()->count(),
                'playlists' => Playlist::query()->count(),
                'activityEvents' => ActivityLog::query()->count(),
            ],
            'topArtists' => ArtistResource::collection(
                Artist::query()->withCount('followedByUsers')->orderByDesc('followed_by_users_count')->limit(5)->get()
            ),
            'topSongs' => SongResource::collection(
                Song::query()->with('album.artist')->withCount('favoritedByUsers')->orderByDesc('favorited_by_users_count')->limit(5)->get()
            ),
            'recentActivity' => ActivityLogResource::collection(
                ActivityLog::query()->with('user')->latest()->limit(8)->get()
            ),
        ]);
    }

    public function users(): JsonResponse
    {
        $users = User::query()
            ->withCount(['playlists', 'favoriteSongs', 'followedArtists'])
            ->latest()
            ->paginate(20);

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
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['isActive'] ?? true,
            'two_factor_enabled' => false,
        ]);

        $this->activity->record($request->user(), 'admin.user_created', 'user', $user);

        return response()->json(['user' => new UserResource($user)], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', Password::min(8)],
            'role' => ['sometimes', Rule::in(['user', 'admin'])],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'role' => $data['role'] ?? $user->role,
            'is_active' => $data['isActive'] ?? $user->is_active,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
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

        return response()->json([
            'user' => new UserResource($user->loadCount(['playlists', 'favoriteSongs', 'followedArtists'])),
            'stats' => [
                'playlists' => $user->playlists()->count(),
                'favorites' => $user->favoriteSongs()->count(),
                'followedArtists' => $user->followedArtists()->count(),
            ],
            'favoriteTracks' => $this->safeTracksByIds($favoriteIds),
            'followedArtists' => $this->safeArtistsByIds($followedIds),
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

        return response()->json([
            'playlist' => new PlaylistResource($playlist),
            'tracks' => $this->safeTracksByIds($spotifyTrackIds),
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
}
