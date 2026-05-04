<?php

namespace App\Http\Controllers;

use App\Http\Resources\ArtistResource;
use App\Http\Resources\PlaylistResource;
use App\Http\Resources\SongResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'playlists' => $user->playlists()->count(),
                'favorites' => $user->favoriteSongs()->count(),
                'followedArtists' => $user->followedArtists()->count(),
            ],
            'followedArtists' => ArtistResource::collection(
                $user->followedArtists()->limit(8)->get()
            ),
            'playlists' => PlaylistResource::collection(
                $user->playlists()->withCount('songs')->latest()->limit(8)->get()
            ),
            'favoriteSongs' => SongResource::collection(
                $user->favoriteSongs()->with('album.artist')->limit(10)->get()
            ),
        ]);
    }
}
