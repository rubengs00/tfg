<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlaylistResource;
use App\Http\Resources\UserResource;
use App\Services\SpotifyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $playlistCount = $user->playlists()->count();
        $favoriteIds = $user->favoriteSongs()
            ->whereNotNull('songs.spotify_id')
            ->orderByDesc('favorite_songs.created_at')
            ->limit(10)
            ->pluck('songs.spotify_id')
            ->values()
            ->all();
        $followedIds = $user->followedArtists()
            ->whereNotNull('artists.spotify_id')
            ->orderByDesc('followed_artists.created_at')
            ->limit(10)
            ->pluck('artists.spotify_id')
            ->values()
            ->all();

        try {
            $favoriteTracks = $this->spotify->getTracksByIds($favoriteIds);
        } catch (\Throwable $e) {
            $favoriteTracks = [];
        }

        try {
            $followedArtists = $this->spotify->getArtistsByIds($followedIds);
        } catch (\Throwable $e) {
            $followedArtists = [];
        }

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'playlists' => $playlistCount,
                'favorites' => $user->favoriteSongs()->count(),
                'followedArtists' => $user->followedArtists()->count(),
            ],
            'playlists' => PlaylistResource::collection(
                $user->playlists()->withCount('songs')->latest()->limit(8)->get()
            ),
            'favoriteTracks' => $favoriteTracks,
            'followedArtists' => $followedArtists,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->name = $validated['name'];

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = asset('storage/' . $path);
        }

        $user->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
