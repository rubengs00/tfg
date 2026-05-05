<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Song;
use App\Models\ApiToken;
use App\Models\TwoFactorChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MusicHubApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_catalog_returns_spotify_payload_shape(): void
    {
        $this->fakeSpotifyCatalog();

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('source', 'spotify')
            ->assertJsonStructure([
                'genre',
                'artists' => [['id', 'name']],
                'albums' => [['id', 'name']],
                'tracks' => [['id', 'name']],
            ]);
    }

    public function test_login_returns_session_and_profile_after_authentication(): void
    {
        $this->seed();

        $session = $this->postJson('/api/auth/login', [
            'email' => 'demo@musichub.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@musichub.local')
            ->assertJsonStructure(['token', 'user'])
            ->json();

        $this->withToken($session['token'])
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('stats.favorites', 6)
            ->assertJsonPath('stats.playlists', 1);
    }

    public function test_register_creates_a_user_and_requires_two_factor_before_session(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'NUEVO@musichub.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'nuevo@musichub.local')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.twoFactorEnabled', true)
            ->assertJsonPath('requiresTwoFactor', true)
            ->assertJsonStructure([
                'challengeId',
                'expiresAt',
                'resendAvailableAt',
                'attemptsRemaining',
                'debugCode',
                'user',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@musichub.local',
            'role' => 'user',
            'two_factor_enabled' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertDatabaseCount('two_factor_challenges', 1);

        $originalChallengeId = $response->json('challengeId');
        $challenge = TwoFactorChallenge::query()->firstOrFail();
        $this->assertNotSame($response->json('debugCode'), $challenge->code_hash);

        $wrongCode = $response->json('debugCode') === '000000' ? '111111' : '000000';

        $this->postJson('/api/auth/verify-2fa', [
            'challengeId' => $originalChallengeId,
            'code' => $wrongCode,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('two_factor_challenges', [
            'id' => $originalChallengeId,
            'attempts_count' => 1,
            'consumed_at' => null,
        ]);

        TwoFactorChallenge::query()
            ->where('id', $originalChallengeId)
            ->update([
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ]);

        $resend = $this->postJson('/api/auth/resend-2fa', [
            'challengeId' => $originalChallengeId,
        ])
            ->assertOk()
            ->assertJsonPath('requiresTwoFactor', true)
            ->assertJsonStructure(['challengeId', 'expiresAt', 'resendAvailableAt', 'debugCode'])
            ->json();

        $this->assertDatabaseCount('two_factor_challenges', 2);
        $this->assertNotNull(TwoFactorChallenge::query()->find($originalChallengeId)?->consumed_at);

        $session = $this->postJson('/api/auth/verify-2fa', [
            'challengeId' => $resend['challengeId'],
            'code' => $resend['debugCode'],
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'nuevo@musichub.local')
            ->assertJsonStructure(['token', 'tokenType', 'expiresAt', 'user'])
            ->json();

        $this->assertDatabaseCount('api_tokens', 1);
        $this->assertTrue(ApiToken::query()->where('token_hash', hash('sha256', $session['token']))->exists());
        $this->assertNotNull(TwoFactorChallenge::query()->find($resend['challengeId'])?->consumed_at);

        $this->withToken($session['token'])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'nuevo@musichub.local');
    }

    public function test_admin_stats_are_protected(): void
    {
        $this->seed();

        $this->getJson('/api/admin/stats')->assertUnauthorized();

        $admin = User::query()->where('email', 'admin@musichub.local')->firstOrFail();
        $demo = User::query()->where('email', 'demo@musichub.local')->firstOrFail();
        $token = bin2hex(random_bytes(32));
        $admin->apiTokens()->create([
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('totals.users', 2)
            ->assertJsonPath('health.admins', 1)
            ->assertJsonPath('health.standardUsers', 1)
            ->assertJsonPath('health.errors24h', 1)
            ->assertJsonPath('recentErrors.0.action', 'api.request_failed')
            ->assertJsonStructure([
                'totals' => ['users', 'artists', 'songs', 'playlists', 'activityEvents'],
                'health' => ['admins', 'standardUsers', 'activeUsers', 'inactiveUsers', 'twoFactorUsers', 'newUsers7d', 'errors24h'],
                'activitySeries' => [['date', 'label', 'total']],
                'topPlaylists',
                'recentErrors',
            ]);

        $this->withToken($token)
            ->patchJson("/api/admin/users/{$demo->id}", [
                'twoFactorEnabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('user.twoFactorEnabled', true);

        $this->assertDatabaseHas('users', [
            'id' => $demo->id,
            'two_factor_enabled' => true,
        ]);

        $this->withToken($token)
            ->patchJson("/api/admin/users/{$admin->id}", [
                'isActive' => false,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_followed_artists_and_favorites_are_reflected_in_profile_and_library(): void
    {
        $this->seed();
        $this->fakeSpotifyCatalog();

        $user = User::query()->where('email', 'demo@musichub.local')->firstOrFail();
        $token = bin2hex(random_bytes(32));
        $user->apiTokens()->create([
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)
            ->postJson('/api/me/favorites', [
                'spotifyTrackId' => 'track12345678901234567',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/me/followed-artists', [
                'spotifyArtistId' => 'artist1234567890123456',
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('favoriteTracks.0.id', 'track12345678901234567')
            ->assertJsonPath('followedArtists.0.id', 'artist1234567890123456');

        $this->withToken($token)
            ->getJson('/api/me/favorites')
            ->assertOk()
            ->assertJsonPath('tracks.0.id', 'track12345678901234567');

        $this->withToken($token)
            ->getJson('/api/me/followed-artists')
            ->assertOk()
            ->assertJsonPath('artists.0.id', 'artist1234567890123456');
    }

    public function test_artist_detail_adds_local_followers_to_spotify_count(): void
    {
        $this->seed();
        $this->fakeSpotifyCatalog();

        $user = User::query()->where('email', 'demo@musichub.local')->firstOrFail();
        $token = bin2hex(random_bytes(32));
        $user->apiTokens()->create([
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)
            ->postJson('/api/me/followed-artists', [
                'spotifyArtistId' => 'artist1234567890123456',
            ])
            ->assertOk();

        $this->getJson('/api/artists/artist1234567890123456')
            ->assertOk()
            ->assertJsonPath('artist.followers.total', 123457);
    }

    public function test_spotify_search_returns_live_remote_ids(): void
    {
        config([
            'services.spotify.client_id' => 'spotify-test-client',
            'services.spotify.client_secret' => 'spotify-test-secret',
        ]);

        $this->fakeSpotifyCatalog();

        $this->getJson('/api/search?q=rosalia')
            ->assertOk()
            ->assertJsonPath('source', 'spotify')
            ->assertJsonPath('artists.0.id', 'artist1234567890123456')
            ->assertJsonPath('albums.0.id', 'album12345678901234567')
            ->assertJsonPath('tracks.0.id', 'track12345678901234567');
    }

    public function test_spotify_sync_command_imports_catalog_into_local_database(): void
    {
        config([
            'services.spotify.client_id' => 'spotify-test-client',
            'services.spotify.client_secret' => 'spotify-test-secret',
        ]);

        Http::fake([
            'https://accounts.spotify.com/api/token' => Http::response([
                'access_token' => 'spotify-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://api.spotify.com/v1/artists?*' => Http::response([
                'artists' => [[
                    'id' => 'artist1234567890123456',
                    'name' => 'Rosalia',
                    'genres' => ['pop'],
                    'followers' => ['total' => 123456],
                    'images' => [['url' => 'https://example.com/artist.jpg']],
                    'popularity' => 92,
                ]],
            ]),
            'https://api.spotify.com/v1/artists/artist1234567890123456/albums*' => Http::response([
                'items' => [],
            ]),
            'https://api.spotify.com/v1/artists/artist1234567890123456*' => Http::response([
                'id' => 'artist1234567890123456',
                'name' => 'Rosalia',
                'genres' => ['pop'],
                'followers' => ['total' => 123456],
                'images' => [['url' => 'https://example.com/artist.jpg']],
                'popularity' => 92,
            ]),
            'https://api.spotify.com/v1/tracks/track12345678901234567*' => Http::response([
                'id' => 'track12345678901234567',
                'name' => 'Saoko',
                'duration_ms' => 137000,
                'preview_url' => 'https://example.com/saoko.mp3',
                'explicit' => false,
                'popularity' => 88,
                'track_number' => 1,
                'artists' => [[
                    'id' => 'artist1234567890123456',
                    'name' => 'Rosalia',
                ]],
                'album' => [
                    'id' => 'album12345678901234567',
                    'name' => 'Motomami',
                    'release_date' => '2022-03-18',
                    'total_tracks' => 16,
                    'images' => [['url' => 'https://example.com/album.jpg']],
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                    ]],
                ],
            ]),
            'https://api.spotify.com/v1/tracks?*' => Http::response([
                'tracks' => [[
                    'id' => 'track12345678901234567',
                    'name' => 'Saoko',
                    'duration_ms' => 137000,
                    'preview_url' => 'https://example.com/saoko.mp3',
                    'explicit' => false,
                    'popularity' => 88,
                    'track_number' => 1,
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                    ]],
                    'album' => [
                        'id' => 'album12345678901234567',
                        'name' => 'Motomami',
                        'release_date' => '2022-03-18',
                        'total_tracks' => 16,
                        'images' => [['url' => 'https://example.com/album.jpg']],
                        'artists' => [[
                            'id' => 'artist1234567890123456',
                            'name' => 'Rosalia',
                        ]],
                    ],
                ]],
            ]),
            'https://api.spotify.com/v1/search*' => Http::response([
                'artists' => [
                    'items' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                        'genres' => ['pop'],
                        'followers' => ['total' => 123456],
                        'images' => [['url' => 'https://example.com/artist.jpg']],
                        'popularity' => 92,
                    ]],
                ],
            ]),
            'https://api.spotify.com/v1/artists/artist1234567890123456/albums*' => Http::response([
                'items' => [[
                    'id' => 'album12345678901234567',
                    'name' => 'Motomami',
                    'release_date' => '2022-03-18',
                    'total_tracks' => 1,
                    'images' => [['url' => 'https://example.com/album.jpg']],
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                    ]],
                ]],
            ]),
            'https://api.spotify.com/v1/artists/artist1234567890123456*' => Http::response([
                'id' => 'artist1234567890123456',
                'name' => 'Rosalia',
                'genres' => ['pop'],
                'followers' => ['total' => 123456],
                'images' => [['url' => 'https://example.com/artist.jpg']],
                'popularity' => 92,
            ]),
            'https://api.spotify.com/v1/albums/album12345678901234567/tracks*' => Http::response([
                'items' => [[
                    'id' => 'track12345678901234567',
                    'name' => 'Saoko',
                    'duration_ms' => 137000,
                    'preview_url' => 'https://example.com/saoko.mp3',
                    'explicit' => false,
                    'popularity' => 88,
                    'track_number' => 1,
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                    ]],
                    'album' => [
                        'id' => 'album12345678901234567',
                        'name' => 'Motomami',
                        'release_date' => '2022-03-18',
                        'total_tracks' => 1,
                        'images' => [['url' => 'https://example.com/album.jpg']],
                        'artists' => [[
                            'id' => 'artist1234567890123456',
                            'name' => 'Rosalia',
                        ]],
                    ],
                ]],
            ]),
            'https://api.spotify.com/v1/albums/album12345678901234567*' => Http::response([
                'id' => 'album12345678901234567',
                'name' => 'Motomami',
                'release_date' => '2022-03-18',
                'total_tracks' => 1,
                'images' => [['url' => 'https://example.com/album.jpg']],
                'artists' => [[
                    'id' => 'artist1234567890123456',
                    'name' => 'Rosalia',
                ]],
            ]),
        ]);

        $this->artisan('spotify:sync-catalog', [
            '--artist' => ['Rosalia'],
            '--albums' => 1,
        ])->assertSuccessful();

        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('albums', 1);
        $this->assertDatabaseCount('songs', 1);
        $this->assertDatabaseHas('artists', ['spotify_id' => 'artist1234567890123456']);
        $this->assertDatabaseHas('albums', ['spotify_id' => 'album12345678901234567']);
        $this->assertDatabaseHas('songs', ['spotify_id' => 'track12345678901234567']);
    }

    public function test_favorites_are_returned_newest_first_from_local_catalog(): void
    {
        $this->seed();

        config([
            'services.spotify.client_id' => null,
            'services.spotify.client_secret' => null,
        ]);

        $user = User::query()->where('email', 'demo@musichub.local')->firstOrFail();
        $songs = Song::query()->whereNotNull('spotify_id')->limit(2)->get();
        $oldSong = $songs[0];
        $newSong = $songs[1];

        DB::table('favorite_songs')->where([
            'user_id' => $user->id,
            'song_id' => $oldSong->id,
        ])->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        DB::table('favorite_songs')->where([
            'user_id' => $user->id,
            'song_id' => $newSong->id,
        ])->update([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = bin2hex(random_bytes(32));
        $user->apiTokens()->create([
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
        ]);

        $this->withToken($token)
            ->getJson('/api/me/favorites')
            ->assertOk()
            ->assertJsonPath('tracks.0.id', $newSong->spotify_id);
    }

    public function test_playlist_update_cover_and_tracks_are_persistent(): void
    {
        $this->seed();
        $this->fakeSpotifyCatalog();

        $user = User::query()->where('email', 'demo@musichub.local')->firstOrFail();
        $token = bin2hex(random_bytes(32));
        $user->apiTokens()->create([
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
        ]);

        $playlist = $this->withToken($token)
            ->postJson('/api/me/playlists', [
                'name' => 'Playlist Persistente',
                'description' => 'Antes',
            ])
            ->assertCreated()
            ->assertJsonPath('playlist.name', 'Playlist Persistente')
            ->json('playlist');

        $playlistId = $playlist['id'];

        $this->withToken($token)
            ->postJson("/api/me/playlists/{$playlistId}/tracks", [
                'spotifyTrackId' => 'track12345678901234567',
            ])
            ->assertOk()
            ->assertJsonPath('playlist.songsCount', 1);

        $this->withToken($token)
            ->getJson("/api/me/playlists/{$playlistId}")
            ->assertOk()
            ->assertJsonPath('playlist.songsCount', 1)
            ->assertJsonPath('tracks.0.id', 'track12345678901234567');

        $publicDiskRoot = storage_path('framework/testing/disks/public');
        config([
            'filesystems.disks.public.root' => $publicDiskRoot,
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);

        $cover = UploadedFile::fake()->createWithContent(
            'cover.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lcl4OQAAAABJRU5ErkJggg==')
        );

        $coverUrl = $this->withToken($token)
            ->post("/api/me/playlists/{$playlistId}", [
                'name' => 'Playlist Editada',
                'description' => 'Despues',
                'cover' => $cover,
                '_method' => 'PATCH',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('playlist.name', 'Playlist Editada')
            ->assertJsonPath('playlist.description', 'Despues')
            ->json('playlist.coverUrl');

        $this->assertStringContainsString('/storage/playlists/playlist-'.$playlistId.'-', $coverUrl);
        $this->assertFileExists(
            $publicDiskRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, Str::after($coverUrl, '/storage/'))
        );

        $this->withToken($token)
            ->getJson("/api/me/playlists/{$playlistId}")
            ->assertOk()
            ->assertJsonPath('playlist.name', 'Playlist Editada')
            ->assertJsonPath('playlist.coverUrl', $coverUrl)
            ->assertJsonPath('tracks.0.id', 'track12345678901234567');
    }

    public function test_profile_update_saves_changes_to_database(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'demo@musichub.local')->firstOrFail();
        $token = bin2hex(random_bytes(32));
        $user->apiTokens()->create([
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
            'abilities' => ['*'],
            'expires_at' => now()->addHour(),
        ]);

        // Actualizar nombre con POST + _method=PUT (como lo hace Angular)
        $response = $this->withToken($token)
            ->postJson('/api/me/profile', [
                'name' => 'Nuevo Nombre',
                '_method' => 'PUT',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nuevo Nombre')
            ->assertJsonPath('message', 'Perfil actualizado correctamente');

        // Verificar que el cambio se guardó en BD
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nuevo Nombre',
        ]);

        // Verificar que al obtener el perfil, refleja el cambio
        $this->withToken($token)
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('user.name', 'Nuevo Nombre');

        // Prueba 2: Cambiar nombre nuevamente
        $response2 = $this->withToken($token)
            ->postJson('/api/me/profile', [
                'name' => 'Nombre Actualizado Dos Veces',
                '_method' => 'PUT',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nombre Actualizado Dos Veces');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre Actualizado Dos Veces',
        ]);

        $publicDiskRoot = storage_path('framework/testing/disks/public');
        config([
            'filesystems.disks.public.root' => $publicDiskRoot,
            'filesystems.disks.public.url' => 'http://localhost/storage',
        ]);

        $avatar = UploadedFile::fake()->createWithContent(
            'avatar.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lcl4OQAAAABJRU5ErkJggg==')
        );

        $avatarUrl = $this->withToken($token)
            ->post('/api/me/profile', [
                'name' => 'Nombre Con Avatar',
                'avatar' => $avatar,
                '_method' => 'PUT',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nombre Con Avatar')
            ->json('user.avatarUrl');

        $this->assertStringContainsString('/storage/avatars/user-'.$user->id.'-', $avatarUrl);
        $this->assertFileExists(
            $publicDiskRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, Str::after($avatarUrl, '/storage/'))
        );
    }

    private function fakeSpotifyCatalog(): void
    {
        config([
            'services.spotify.client_id' => 'spotify-test-client',
            'services.spotify.client_secret' => 'spotify-test-secret',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if ($url === 'https://accounts.spotify.com/api/token') {
                return Http::response([
                    'access_token' => 'spotify-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ]);
            }

            if (str_contains($url, '/v1/search')) {
                return Http::response([
                    'artists' => [
                        'items' => [[
                            'id' => 'artist1234567890123456',
                            'name' => 'Rosalia',
                            'genres' => ['pop'],
                            'followers' => ['total' => 123456],
                            'images' => [['url' => 'https://example.com/artist.jpg']],
                            'popularity' => 92,
                        ]],
                    ],
                    'albums' => [
                        'items' => [[
                            'id' => 'album12345678901234567',
                            'name' => 'Motomami',
                            'release_date' => '2022-03-18',
                            'total_tracks' => 16,
                            'images' => [['url' => 'https://example.com/album.jpg']],
                            'artists' => [[
                                'id' => 'artist1234567890123456',
                                'name' => 'Rosalia',
                            ]],
                        ]],
                    ],
                    'tracks' => [
                        'items' => [[
                            'id' => 'track12345678901234567',
                            'name' => 'Saoko',
                            'duration_ms' => 137000,
                            'preview_url' => 'https://example.com/saoko.mp3',
                            'explicit' => false,
                            'popularity' => 88,
                            'track_number' => 1,
                            'artists' => [[
                                'id' => 'artist1234567890123456',
                                'name' => 'Rosalia',
                            ]],
                            'album' => [
                                'id' => 'album12345678901234567',
                                'name' => 'Motomami',
                                'release_date' => '2022-03-18',
                                'total_tracks' => 16,
                                'images' => [['url' => 'https://example.com/album.jpg']],
                                'artists' => [[
                                    'id' => 'artist1234567890123456',
                                    'name' => 'Rosalia',
                                ]],
                            ],
                        ]],
                    ],
                ]);
            }

            if (str_contains($url, '/v1/tracks/track12345678901234567')) {
                return Http::response([
                    'id' => 'track12345678901234567',
                    'name' => 'Saoko',
                    'duration_ms' => 137000,
                    'preview_url' => 'https://example.com/saoko.mp3',
                    'explicit' => false,
                    'popularity' => 88,
                    'track_number' => 1,
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                    ]],
                    'album' => [
                        'id' => 'album12345678901234567',
                        'name' => 'Motomami',
                        'release_date' => '2022-03-18',
                        'total_tracks' => 16,
                        'images' => [['url' => 'https://example.com/album.jpg']],
                        'artists' => [[
                            'id' => 'artist1234567890123456',
                            'name' => 'Rosalia',
                        ]],
                    ],
                ]);
            }

            if (str_contains($url, '/v1/tracks?')) {
                return Http::response([
                    'tracks' => [[
                        'id' => 'track12345678901234567',
                        'name' => 'Saoko',
                        'duration_ms' => 137000,
                        'preview_url' => 'https://example.com/saoko.mp3',
                        'explicit' => false,
                        'popularity' => 88,
                        'track_number' => 1,
                        'artists' => [[
                            'id' => 'artist1234567890123456',
                            'name' => 'Rosalia',
                        ]],
                        'album' => [
                            'id' => 'album12345678901234567',
                            'name' => 'Motomami',
                            'release_date' => '2022-03-18',
                            'total_tracks' => 16,
                            'images' => [['url' => 'https://example.com/album.jpg']],
                            'artists' => [[
                                'id' => 'artist1234567890123456',
                                'name' => 'Rosalia',
                            ]],
                        ],
                    ]],
                ]);
            }

            if (str_contains($url, '/v1/artists?')) {
                return Http::response([
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                        'genres' => ['pop'],
                        'followers' => ['total' => 123456],
                        'images' => [['url' => 'https://example.com/artist.jpg']],
                        'popularity' => 92,
                    ]],
                ]);
            }

            if (str_contains($url, '/v1/artists/artist1234567890123456/albums')) {
                return Http::response(['items' => []]);
            }

            if (str_contains($url, '/v1/artists/artist1234567890123456')) {
                return Http::response([
                    'id' => 'artist1234567890123456',
                    'name' => 'Rosalia',
                    'genres' => ['pop'],
                    'followers' => ['total' => 123456],
                    'images' => [['url' => 'https://example.com/artist.jpg']],
                    'popularity' => 92,
                ]);
            }

            if (str_contains($url, '/v1/albums/album12345678901234567')) {
                return Http::response([
                    'id' => 'album12345678901234567',
                    'name' => 'Motomami',
                    'release_date' => '2022-03-18',
                    'total_tracks' => 16,
                    'images' => [['url' => 'https://example.com/album.jpg']],
                    'artists' => [[
                        'id' => 'artist1234567890123456',
                        'name' => 'Rosalia',
                    ]],
                ]);
            }

            return Http::response([], 404);
        });
    }
}
