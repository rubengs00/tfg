import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';

import { LibraryService } from '../../core/library.service';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-favorites-page',
  standalone: true,
  imports: [CommonModule, SongRowComponent],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">Tu biblioteca</span>
        <h1>Favoritas</h1>
      </div>
      <p>Todas las canciones favoritas de tu cuenta, siempre sincronizadas con Spotify.</p>
    </section>

    <section class="content-section">
      @if (loading()) {
        <div class="empty-state">Cargando favoritas...</div>
      } @else if (!tracks().length) {
        <div class="empty-state">Todavia no has anadido canciones a favoritos.</div>
      } @else {
        <div class="song-list">
          @for (track of tracks(); track track.id; let i = $index) {
            <app-song-row [track]="track" [index]="i + 1" [showAlbum]="true" />
          }
        </div>
      }
    </section>
  `,
})
export class FavoritesPageComponent {
  private readonly library = inject(LibraryService);

  readonly tracks = this.library.favoriteTracks;
  readonly loading = signal(true);

  constructor() {
    this.library.favorites().subscribe({
      next: () => this.loading.set(false),
      error: () => this.loading.set(false),
    });
  }
}
