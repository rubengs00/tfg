<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ArtistResource;
use App\Http\Resources\SongResource;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity)
    {
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
        return response()->json([
            'users' => UserResource::collection(User::query()->latest()->paginate(20)),
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
            'two_factor_enabled' => true,
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
            $query->where('action', 'like', '%'.$request->query('action').'%');
        }

        return response()->json([
            'activity' => ActivityLogResource::collection($query->paginate(30)),
        ]);
    }
}
