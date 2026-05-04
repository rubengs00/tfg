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
              [subtitle]="artist.genre ?? 'Artista'"
              [imageUrl]="artist.imageUrl"
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
              [title]="album.title"
              [subtitle]="album.artist?.name ?? 'Album'"
              [imageUrl]="album.coverUrl"
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
          @for (song of data().songs; track song.id; let i = $index) {
            <app-song-row [song]="song" [index]="i + 1" [showAlbum]="true" />
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

  readonly data = signal<HomeData>({ artists: [], albums: [], songs: [] });
  readonly loading = signal(true);
  readonly title = signal('Descubre nueva musica');
  readonly subtitle = signal('Explora artistas, albumes y previews inspirados en Spotify.');

  constructor() {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const query = params.get('q')?.trim() ?? '';
      this.loading.set(true);

      if (query) {
        this.title.set(`Resultados para "${query}"`);
        this.subtitle.set('Busqueda local con soporte para Spotify API cuando configures las credenciales.');
        this.catalog.search(query).subscribe((results) => {
          this.data.set(results);
          this.loading.set(false);
        });
        return;
      }

      this.title.set('Descubre nueva musica');
      this.subtitle.set('Explora artistas, albumes y previews inspirados en Spotify.');
      this.catalog.home().subscribe((home) => {
        this.data.set(home);
        this.loading.set(false);
      });
    });
  }
}
