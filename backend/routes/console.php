<?php

use App\Services\SpotifyCatalogService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('spotify:sync-catalog {--artist=* : Artist names or Spotify artist IDs} {--albums=3 : Albums or singles to import per artist}', function (): int {
    /** @var SpotifyCatalogService $spotify */
    $spotify = app(SpotifyCatalogService::class);

    if (! $spotify->enabled()) {
        $this->error('Configura SPOTIFY_CLIENT_ID y SPOTIFY_CLIENT_SECRET antes de sincronizar el catalogo.');

        return Command::FAILURE;
    }

    $artistOptions = collect((array) $this->option('artist'))
        ->map(fn (mixed $artist): string => trim((string) $artist))
        ->filter()
        ->values()
        ->all();

    $counts = $spotify->syncSeedCatalog(
        $artistOptions !== [] ? $artistOptions : null,
        max((int) $this->option('albums'), 1),
    );

    $this->info(sprintf(
        'Catalogo sincronizado desde Spotify: %d artistas, %d albumes y %d canciones.',
        $counts['artists'],
        $counts['albums'],
        $counts['songs'],
    ));

    return Command::SUCCESS;
})->purpose('Import artists, albums and songs from Spotify into the local catalog');
