<?php

namespace App\Http\Controllers;

use App\Services\SpotifyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // ✅ Contadores
        $playlistCount = $user->playlists()->count();
        $favoriteCount = DB::table('favorite_songs')
            ->where('user_id', $user->id)
            ->count();

        $followedCount = DB::table('followed_artists')
            ->where('user_id', $user->id)
            ->count();

        // ✅ Favoritos
        $favoriteIds = DB::table('favorite_songs')
            ->where('user_id', $user->id)
            ->pluck('spotify_track_id')
            ->take(10)
            ->toArray();

        try {
            $favoriteTracks = $this->spotify->getTracksByIds($favoriteIds);
        } catch (\Throwable $e) {
            $favoriteTracks = [];
        }

        // ✅ Artistas seguidos
        $followedIds = DB::table('followed_artists')
            ->where('user_id', $user->id)
            ->pluck('spotify_artist_id')
            ->take(10)
            ->toArray();

        // Si Spotify no está configurado o devuelve error (403, etc.), no debería tumbar el perfil.
        try {
            $followedArtists = $this->spotify->getArtistsByIds($followedIds);
        } catch (\Throwable $e) {
            $followedArtists = [];
        }

        return response()->json([
            'user' => $user,
            'stats' => [
                'playlists' => $playlistCount,
                'favorites' => $favoriteCount,
                'followedArtists' => $followedCount,
            ],
            'playlists' => $user->playlists()->latest()->limit(8)->get(),
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
            'user' => $user,
        ]);
    }
}
