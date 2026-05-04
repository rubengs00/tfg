<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $artists = collect([
            [
                'name' => 'Elandra Nova',
                'genre' => 'Pop electronico',
                'followers' => 1842300,
                'popularity' => 96,
                'image_url' => 'https://images.unsplash.com/photo-1516280440614-37939bbacd81?auto=format&fit=crop&w=800&q=80',
                'bio' => 'Voz luminosa, sintetizadores pulidos y estribillos de festival.',
                'albums' => [
                    [
                        'title' => 'Afterglow City',
                        'release_year' => 2026,
                        'cover_url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?auto=format&fit=crop&w=800&q=80',
                        'songs' => ['Neon Hearts', 'Afterglow', 'Northline', 'Static Love'],
                    ],
                    [
                        'title' => 'Glass Summer',
                        'release_year' => 2024,
                        'cover_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=800&q=80',
                        'songs' => ['Crystal Road', 'Safe Frequency', 'Ocean Lights'],
                    ],
                ],
            ],
            [
                'name' => 'The Midnight Lines',
                'genre' => 'Indie rock',
                'followers' => 742900,
                'popularity' => 89,
                'image_url' => 'https://images.unsplash.com/photo-1511379938547-c1f69419868d?auto=format&fit=crop&w=800&q=80',
                'bio' => 'Guitarras nocturnas, baterias secas y melodias para carretera.',
                'albums' => [
                    [
                        'title' => 'Room 404',
                        'release_year' => 2025,
                        'cover_url' => 'https://images.unsplash.com/photo-1511735111819-9a3f7709049c?auto=format&fit=crop&w=800&q=80',
                        'songs' => ['Hotel Static', 'Wrong Exit', 'Room 404', 'Last Lift'],
                    ],
                ],
            ],
            [
                'name' => 'Kairo Pulse',
                'genre' => 'Hip hop',
                'followers' => 1280400,
                'popularity' => 92,
                'image_url' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?auto=format&fit=crop&w=800&q=80',
                'bio' => 'Bajos densos, flows precisos y produccion con brillo futurista.',
                'albums' => [
                    [
                        'title' => 'Metro Dreams',
                        'release_year' => 2026,
                        'cover_url' => 'https://images.unsplash.com/photo-1506157786151-b8491531f063?auto=format&fit=crop&w=800&q=80',
                        'songs' => ['Metro Dreams', 'Gold Token', 'Late Checkout', 'New Signal'],
                    ],
                ],
            ],
            [
                'name' => 'Marble Coast',
                'genre' => 'Dance',
                'followers' => 983500,
                'popularity' => 87,
                'image_url' => 'https://images.unsplash.com/photo-1507874457470-272b3c8d8ee2?auto=format&fit=crop&w=800&q=80',
                'bio' => 'House mediterraneo y ritmos limpios para sesiones largas.',
                'albums' => [
                    [
                        'title' => 'Blue Hour Club',
                        'release_year' => 2025,
                        'cover_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80',
                        'songs' => ['Blue Hour Club', 'Warm Kick', 'Terrace View', 'Last Drop'],
                    ],
                ],
            ],
            [
                'name' => 'Sofia Vale',
                'genre' => 'R&B',
                'followers' => 653100,
                'popularity' => 84,
                'image_url' => 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=800&q=80',
                'bio' => 'R&B suave con produccion minimalista y letras cercanas.',
                'albums' => [
                    [
                        'title' => 'Soft Focus',
                        'release_year' => 2024,
                        'cover_url' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=800&q=80',
                        'songs' => ['Soft Focus', 'Velvet Call', 'Rain Delay'],
                    ],
                ],
            ],
        ]);

        $previewUrls = [
            'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
            'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
            'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
            'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
        ];

        $artists->each(function (array $artistData) use ($previewUrls): void {
            $artist = Artist::query()->create([
                'spotify_id' => 'demo_artist_'.Str::slug($artistData['name']),
                'name' => $artistData['name'],
                'slug' => Str::slug($artistData['name']),
                'genre' => $artistData['genre'],
                'followers' => $artistData['followers'],
                'image_url' => $artistData['image_url'],
                'bio' => $artistData['bio'],
                'popularity' => $artistData['popularity'],
            ]);

            collect($artistData['albums'])->each(function (array $albumData) use ($artist, $previewUrls): void {
                $album = Album::query()->create([
                    'spotify_id' => 'demo_album_'.Str::slug($artist->name.' '.$albumData['title']),
                    'artist_id' => $artist->id,
                    'title' => $albumData['title'],
                    'slug' => Str::slug($artist->name.' '.$albumData['title']),
                    'cover_url' => $albumData['cover_url'],
                    'release_year' => $albumData['release_year'],
                    'total_tracks' => count($albumData['songs']),
                ]);

                collect($albumData['songs'])->each(function (string $songTitle, int $index) use ($album, $previewUrls): void {
                    Song::query()->create([
                        'spotify_id' => 'demo_song_'.Str::slug($album->title.' '.$songTitle),
                        'album_id' => $album->id,
                        'title' => $songTitle,
                        'duration_seconds' => 162 + (($index + $album->id) * 11),
                        'preview_url' => $previewUrls[$index % count($previewUrls)],
                        'track_number' => $index + 1,
                        'explicit' => false,
                        'popularity' => 95 - ($index * 3),
                    ]);
                });
            });
        });
    }
}
