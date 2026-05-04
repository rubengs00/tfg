<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['status' => 'ok', 'app' => config('app.name')]);

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-2fa', [AuthController::class, 'verifyTwoFactor']);

    Route::middleware('auth.api')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/home', [CatalogController::class, 'home']);
Route::get('/search', [CatalogController::class, 'search']);
Route::get('/artists', [CatalogController::class, 'artists']);
Route::get('/artists/{artist}', [CatalogController::class, 'artist']);
Route::get('/albums', [CatalogController::class, 'albums']);
Route::get('/albums/{album}', [CatalogController::class, 'album']);
Route::get('/songs', [CatalogController::class, 'songs']);

Route::middleware('auth.api')->prefix('me')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show']);

    Route::get('/favorites', [LibraryController::class, 'favorites']);
    Route::put('/favorites/{song}', [LibraryController::class, 'addFavorite']);
    Route::delete('/favorites/{song}', [LibraryController::class, 'removeFavorite']);

    Route::get('/followed-artists', [LibraryController::class, 'followedArtists']);
    Route::put('/followed-artists/{artist}', [LibraryController::class, 'followArtist']);
    Route::delete('/followed-artists/{artist}', [LibraryController::class, 'unfollowArtist']);

    Route::get('/playlists', [LibraryController::class, 'playlists']);
    Route::post('/playlists', [LibraryController::class, 'createPlaylist']);
    Route::get('/playlists/{playlist}', [LibraryController::class, 'playlist']);
    Route::patch('/playlists/{playlist}', [LibraryController::class, 'updatePlaylist']);
    Route::delete('/playlists/{playlist}', [LibraryController::class, 'deletePlaylist']);
    Route::put('/playlists/{playlist}/songs/{song}', [LibraryController::class, 'addSongToPlaylist']);
    Route::delete('/playlists/{playlist}/songs/{song}', [LibraryController::class, 'removeSongFromPlaylist']);
});

Route::middleware(['auth.api', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
    Route::get('/activity', [AdminController::class, 'activity']);
});
