<?php

namespace App\Console\Commands;

use App\Services\SpotifyCatalogService;
use Illuminate\Console\Command;

class SyncSpotifyCatalog extends Command
{
    protected $signature = 'musichub:sync {--albums=3 : Number of albums per artist}';
    protected $description = 'Synchronize Spotify seed catalog into local database';

    public function handle(SpotifyCatalogService $spotify): int
    {
        if (! $spotify->enabled()) {
            $this->error('Spotify credentials are not configured.');
            return self::FAILURE;
        }

        $albumsPerArtist = (int) $this->option('albums');

        $this->info('Starting Spotify catalog synchronization...');

        $result = $spotify->syncSeedCatalog(null, $albumsPerArtist);

        $this->info("Artists synced: {$result['artists']}");
        $this->info("Albums synced: {$result['albums']}");
        $this->info("Songs synced: {$result['songs']}");

        $this->info('Synchronization completed.');

        return self::SUCCESS;
    }
}
