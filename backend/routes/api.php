<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Library\LibraryController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok', 'app' => config('app.name')]);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
    Route::post('/resend-2fa', [AuthController::class, 'resendTwoFactor']);

    Route::middleware('auth.api')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/home', [CatalogController::class, 'home']);
Route::get('/search', [CatalogController::class, 'search']);
Route::get('/artists/{spotifyId}', [CatalogController::class, 'artist']);
Route::get('/albums/{spotifyId}', [CatalogController::class, 'album']);

Route::middleware('auth.api')->prefix('me')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/favorites', [LibraryController::class, 'favorites']);
    Route::post('/favorites', [LibraryController::class, 'addFavorite']);
    Route::delete('/favorites', [LibraryController::class, 'removeFavorite']);

    Route::get('/followed-artists', [LibraryController::class, 'followedArtists']);
    Route::post('/followed-artists', [LibraryController::class, 'followArtist']);
    Route::delete('/followed-artists', [LibraryController::class, 'unfollowArtist']);

    Route::get('/playlists', [LibraryController::class, 'playlists']);
    Route::post('/playlists', [LibraryController::class, 'createPlaylist']);
    Route::get('/playlists/{playlist}', [LibraryController::class, 'playlist']);
    Route::patch('/playlists/{playlist}', [LibraryController::class, 'updatePlaylist']);
    Route::delete('/playlists/{playlist}', [LibraryController::class, 'deletePlaylist']);
    Route::post('/playlists/{playlist}/tracks', [LibraryController::class, 'addTrackToPlaylist']);
    Route::delete('/playlists/{playlist}/tracks', [LibraryController::class, 'removeTrackFromPlaylist']);
});

Route::middleware(['auth.api', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
    Route::get('/users/{user}/library', [AdminController::class, 'userLibrary']);
    Route::get('/users/{user}/playlists/{playlist}', [AdminController::class, 'userPlaylist']);
    Route::delete('/users/{user}/favorites/{spotifyTrackId}', [AdminController::class, 'removeUserFavorite']);
    Route::delete('/users/{user}/followed-artists/{spotifyArtistId}', [AdminController::class, 'removeUserFollowedArtist']);
    Route::delete('/users/{user}/playlists/{playlist}', [AdminController::class, 'deleteUserPlaylist']);
    Route::get('/activity', [AdminController::class, 'activity']);
});
