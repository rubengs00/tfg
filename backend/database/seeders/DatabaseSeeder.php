<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Services\SpotifyCatalogService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Throwable;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin MusicHub',
            'email' => 'admin@musichub.local',
            'password' => 'password',
            'role' => 'admin',
            'avatar_url' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=320&q=80',
            'two_factor_enabled' => true,
        ]);

        $demo = User::query()->create([
            'name' => 'Ruben Demo',
            'email' => 'demo@musichub.local',
            'password' => 'password',
            'role' => 'user',
            'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=320&q=80',
            'two_factor_enabled' => true,
        ]);

        $catalogSource = $this->seedCatalog();

        $demo->favoriteSongs()->sync(Song::query()->limit(6)->pluck('id'));
        $demo->followedArtists()->sync(Artist::query()->limit(4)->pluck('id'));

        $playlist = Playlist::query()->create([
            'user_id' => $demo->id,
            'name' => 'Workout Mix',
            'description' => 'Energia rapida para entrenar.',
            'is_public' => false,
        ]);

        Song::query()->limit(5)->get()->each(function (Song $song, int $index) use ($playlist): void {
            $playlist->songs()->attach($song->id, ['position' => $index + 1]);
        });

        ActivityLog::query()->insert([
            [
                'user_id' => $admin->id,
                'action' => 'admin.seeded',
                'resource_type' => 'system',
                'resource_id' => null,
                'metadata' => json_encode([
                    'message' => $catalogSource === 'spotify'
                        ? 'Catalogo inicial sincronizado desde Spotify'
                        : 'Catalogo demo inicial creado',
                    'source' => $catalogSource,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $demo->id,
                'action' => 'playlist.created',
                'resource_type' => 'playlist',
                'resource_id' => $playlist->id,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedCatalog(): string
    {
        /** @var SpotifyCatalogService $spotify */
        $spotify = app(SpotifyCatalogService::class);

        if (! $spotify->enabled()) {
            // No catálogo demo: solo Spotify real
            return 'spotify-disabled';
        }

        try {
            $counts = $spotify->syncSeedCatalog();

            if (($counts['artists'] ?? 0) > 0 && ($counts['songs'] ?? 0) > 0) {
                return 'spotify';
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        // Si Spotify falla, no usar catálogo demo
        return 'spotify-failed';
    }
}
