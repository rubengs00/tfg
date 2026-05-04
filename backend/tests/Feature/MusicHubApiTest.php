<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_register_creates_a_user_and_returns_a_session(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@musichub.local',
            'password' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'nuevo@musichub.local')
            ->assertJsonPath('user.role', 'user');

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@musichub.local',
            'role' => 'user',
        ]);

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'nuevo@musichub.local');
    }

    public function test_admin_stats_are_protected(): void
    {
        $this->seed();

        $this->getJson('/api/admin/stats')->assertUnauthorized();

        $admin = User::query()->where('email', 'admin@musichub.local')->firstOrFail();
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
            ->assertJsonPath('totals.users', 2);
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
