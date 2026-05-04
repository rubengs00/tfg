import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { CatalogService } from '../../core/catalog.service';
import { Album } from '../../core/models';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-album-page',
  imports: [RouterLink, SongRowComponent],
  template: `
    @if (album(); as currentAlbum) {
      <section class="detail-hero">
        <img class="detail-hero__image" [src]="currentAlbum.coverUrl ?? ''" [alt]="currentAlbum.title" />
        <div>
          <span class="eyebrow">Album</span>
          <h1>{{ currentAlbum.title }}</h1>
          <p>
            <a [routerLink]="['/artists', currentAlbum.artist?.id]">{{ currentAlbum.artist?.name }}</a>
            / {{ currentAlbum.releaseYear }} / {{ currentAlbum.totalTracks }} canciones
          </p>
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Canciones</h2>
        </div>
        <div class="song-list">
          @for (song of currentAlbum.songs ?? []; track song.id; let i = $index) {
            <app-song-row [song]="song" [index]="i + 1" />
          }
        </div>
      </section>
    } @else {
      <div class="empty-state">Cargando album...</div>
    }
  `,
})
export class AlbumPageComponent {
  private readonly catalog = inject(CatalogService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  readonly album = signal<Album | null>(null);

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = Number(params.get('id'));
      this.catalog.album(id).subscribe((album) => this.album.set(album));
    });
  }
}
