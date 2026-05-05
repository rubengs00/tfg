<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;

use App\Http\Resources\PlaylistResource;
use App\Http\Resources\UserResource;
use App\Models\Artist;
use App\Models\Song;
use App\Services\SpotifyCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SpotifyCatalogService $spotify
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

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
            $favoriteTracks = $this->orderTracksByIds(
                $this->spotify->getTracksByIds($favoriteIds),
                $favoriteIds
            );
        } catch (\Throwable $exception) {
            $favoriteTracks = [];
        }

        if (empty($favoriteTracks) && ! empty($favoriteIds)) {
            $favoriteTracks = $this->getLocalTracks($favoriteIds);
        }

        try {
            $followedArtists = $this->spotify->getArtistsByIds($followedIds);
        } catch (\Throwable $exception) {
            $followedArtists = [];
        }

        if (empty($followedArtists) && ! empty($followedIds)) {
            $followedArtists = $this->getLocalArtists($followedIds);
        }

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'playlists' => $user->playlists()->count(),
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
            'avatar' => ['nullable', 'file', 'max:2048'],
        ]);

        $user->name = $validated['name'];

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $extension = $this->validateAvatar($avatar);
            $filename = 'user-'.$user->id.'-'.Str::uuid().'.'.$extension;
            $path = $this->storeAvatar($avatar, $filename);
            $user->avatar_url = $this->publicStorageUrl($path);
        }

        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    private function validateAvatar(UploadedFile $avatar): string
    {
        $imageInfo = @getimagesize($avatar->getRealPath());
        $imageType = $imageInfo[2] ?? null;
        $extensions = [
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        if (! isset($extensions[$imageType])) {
            throw ValidationException::withMessages([
                'avatar' => ['El avatar debe ser una imagen JPG, PNG, GIF o WebP.'],
            ]);
        }

        return $extensions[$imageType];
    }

    private function storeAvatar(UploadedFile $avatar, string $filename): string
    {
        $root = rtrim(
            (string) config('filesystems.disks.public.root', storage_path('app/public')),
            DIRECTORY_SEPARATOR
        );
        $directory = $root.DIRECTORY_SEPARATOR.'avatars';

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw ValidationException::withMessages([
                'avatar' => ['No se pudo preparar el directorio de avatares.'],
            ]);
        }

        $avatar->move($directory, $filename);

        return 'avatars/'.$filename;
    }

    private function publicStorageUrl(string $path): string
    {
        return rtrim(
            (string) config('filesystems.disks.public.url', url('/storage')),
            '/'
        ).'/'.ltrim(str_replace('\\', '/', $path), '/');
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
}
