<?php

namespace App\Console\Commands;

use App\Services\SpotifyCatalogService;
use Illuminate\Console\Command;

class SyncSpotifyCatalog extends Command
{
    protected $signature = 'spotify:sync-catalog {--artist=* : Artist names or Spotify artist IDs} {--albums=3 : Albums or singles to import per artist}';
    protected $description = 'Import artists, albums and songs from Spotify into the local catalog';

    public function handle(SpotifyCatalogService $spotify): int
    {
        if (! $spotify->enabled()) {
            $this->error('Configura SPOTIFY_CLIENT_ID y SPOTIFY_CLIENT_SECRET antes de sincronizar el catalogo.');
            return self::FAILURE;
        }

        $artistOptions = collect((array) $this->option('artist'))
            ->map(fn (mixed $artist): string => trim((string) $artist))
            ->filter()
            ->values()
            ->all();

        $albumsPerArtist = max((int) $this->option('albums'), 1);

        $result = $spotify->syncSeedCatalog(
            $artistOptions !== [] ? $artistOptions : null,
            $albumsPerArtist
        );

        $this->info(sprintf(
            'Catalogo sincronizado desde Spotify: %d artistas, %d albumes y %d canciones.',
            $result['artists'],
            $result['albums'],
            $result['songs'],
        ));

        return self::SUCCESS;
    }
}
