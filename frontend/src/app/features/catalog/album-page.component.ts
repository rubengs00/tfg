import { CommonModule } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { CatalogService } from '../../core/catalog.service';
import { SpotifyAlbum, SpotifyTrack } from '../../core/models';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-album-page',
  standalone: true,
  imports: [CommonModule, SongRowComponent],
  template: `
    @if (!album()) {
      <div class="empty-state">Cargando álbum...</div>
    } @else {
      <section class="page-hero page-hero--album">
        <img
          class="page-hero__cover"
          [src]="album()?.images?.[0]?.url ?? ''"
          [alt]="album()?.name ?? ''"
        />
        <div>
          <span class="eyebrow">Álbum</span>
          <h1>{{ album()?.name }}</h1>
          <p>
            {{ album()?.artists?.[0]?.name ?? '' }} ·
            {{ album()?.release_date ?? '' }} ·
            {{ album()?.total_tracks ?? tracks().length }} canciones
          </p>
        </div>
      </section>

      <section class="content-section">
        <div class="song-list">
          @for (track of tracks(); track track.id; let i = $index) {
            <app-song-row [track]="track" [index]="i + 1" />
          }
        </div>
      </section>
    }
  `,
})
export class AlbumPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly destroyRef = inject(DestroyRef);

  readonly album = signal<SpotifyAlbum | null>(null);
  readonly tracks = signal<SpotifyTrack[]>([]);

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = params.get('id');
      if (!id) return;

      // CatalogService espera ID de Spotify como string
      this.catalog.album(id).subscribe((response) => {
        this.album.set(response.album);
        this.tracks.set(response.tracks);
      });
    });
  }
}
