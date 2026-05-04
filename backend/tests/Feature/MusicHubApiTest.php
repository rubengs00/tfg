<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MusicHubApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_catalog_returns_seeded_music(): void
    {
        $this->seed();

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonStructure([
                'artists' => [['id', 'name', 'genre']],
                'albums' => [['id', 'title', 'artist']],
                'songs' => [['id', 'title', 'album']],
            ]);
    }

    public function test_login_requires_two_factor_and_returns_profile_after_verification(): void
    {
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo@musichub.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJson(['requiresTwoFactor' => true])
            ->json();

        $session = $this->postJson('/api/auth/verify-2fa', [
            'challengeId' => $login['challengeId'],
            'code' => $login['debugCode'],
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@musichub.local')
            ->json();

        $this->withToken($session['token'])
            ->getJson('/api/me/profile')
            ->assertOk()
            ->assertJsonPath('stats.favorites', 6)
            ->assertJsonPath('stats.playlists', 1);
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

    public function test_spotify_search_results_are_synced_with_local_ids(): void
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
            ]),
        ]);

        $response = $this->getJson('/api/search?q=rosalia')
            ->assertOk()
            ->assertJsonPath('source', 'spotify');

        $artistId = $response->json('artists.0.id');
        $albumId = $response->json('albums.0.id');
        $songId = $response->json('songs.0.id');

        $this->assertIsInt($artistId);
        $this->assertIsInt($albumId);
        $this->assertIsInt($songId);

        $this->assertDatabaseHas('artists', ['id' => $artistId, 'spotify_id' => 'artist1234567890123456']);
        $this->assertDatabaseHas('albums', ['id' => $albumId, 'spotify_id' => 'album12345678901234567']);
        $this->assertDatabaseHas('songs', ['id' => $songId, 'spotify_id' => 'track12345678901234567']);
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
            'https://api.spotify.com/v1/artists/artist1234567890123456' => Http::response([
                'id' => 'artist1234567890123456',
                'name' => 'Rosalia',
                'genres' => ['pop'],
                'followers' => ['total' => 123456],
                'images' => [['url' => 'https://example.com/artist.jpg']],
                'popularity' => 92,
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
                'tracks' => [
                    'items' => [[
                        'id' => 'track12345678901234567',
                    ]],
                ],
            ]),
            'https://api.spotify.com/v1/tracks*' => Http::response([
                'tracks' => [[
                    'id' => 'track12345678901234567',
                    'name' => 'Saoko',
                    'duration_ms' => 137000,
                    'preview_url' => 'https://example.com/saoko.mp3',
                    'explicit' => false,
                    'popularity' => 88,
                    'track_number' => 1,
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
        ]);

        $this->artisan('spotify:sync-catalog', [
            '--artist' => ['Rosalia'],
            '--albums' => 1,
        ])->assertSuccessful();

        $this->assertDatabaseCount('artists', 1);
        $this->assertDatabaseCount('albums', 1);
        $this->assertDatabaseCount('songs', 1);
    }
}
