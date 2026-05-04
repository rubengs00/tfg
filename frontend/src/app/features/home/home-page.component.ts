import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';

import { CatalogService } from '../../core/catalog.service';
import { HomeData } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-home-page',
  imports: [MediaCardComponent, SongRowComponent],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">MusicHub</span>
        <h1>{{ title() }}</h1>
      </div>
      <p>{{ subtitle() }}</p>
    </section>

    @if (loading()) {
      <div class="empty-state">Cargando catalogo...</div>
    } @else {
      <section class="content-section">
        <div class="section-heading">
          <h2>Artistas populares</h2>
        </div>

        <div class="media-grid">
          @for (artist of data().artists; track artist.id) {
            <app-media-card
              [title]="artist.name"
              [subtitle]="artist.genres?.[0] ?? 'Artista'"
              [imageUrl]="artist.images?.[0]?.url ?? null"
              [route]="['/artists', artist.id]"
              kind="artist"
              [round]="true"
            />
          }
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Albumes populares</h2>
        </div>
        <div class="media-grid">
          @for (album of data().albums; track album.id) {
            <app-media-card
              [title]="album.name"
              [subtitle]="album.artists?.[0]?.name ?? 'Album'"
              [imageUrl]="album.images?.[0]?.url ?? null"
              [route]="['/albums', album.id]"
              kind="album"
            />
          }
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Canciones destacadas</h2>
        </div>
        <div class="song-list">
          @for (track of data().tracks; track track.id; let i = $index) {
            <app-song-row [track]="track" [index]="i + 1" />
          }
        </div>
      </section>
    }
  `,
})
export class HomePageComponent {
  private readonly catalog = inject(CatalogService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  readonly data = signal<HomeData>({
    source: 'spotify',
    artists: [],
    albums: [],
    tracks: [],
  });

  readonly loading = signal(true);

  readonly title = signal('Descubre nueva musica');
  readonly subtitle = signal(
    'Explora artistas, albumes y previews inspirados en Spotify.'
  );

  constructor() {
    this.route.queryParamMap
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((params) => {
        const query = params.get('q')?.trim() ?? '';
        this.loading.set(true);

        if (query) {
          this.title.set(`Resultados para "${query}"`);
          this.subtitle.set('Busqueda en vivo usando Spotify.');
          this.catalog.search(query).subscribe((results) => {
            this.data.set(results);
            this.loading.set(false);
          });
          return;
        }

        this.title.set('Descubre nueva musica');
        this.subtitle.set(
          'Explora artistas, albumes y previews inspirados en Spotify.'
        );

        this.catalog.home().subscribe((home) => {
          this.data.set(home);
          this.loading.set(false);
        });
      });
  }
}
