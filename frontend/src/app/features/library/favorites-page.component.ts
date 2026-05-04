import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';

import { LibraryService } from '../../core/library.service';
import { SpotifyTrack } from '../../core/models';
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
      <p>Canciones favoritas guardadas como IDs de Spotify en tu cuenta.</p>
    </section>

    <section class="content-section">
      @if (!tracks().length) {
        <div class="empty-state">Todavía no has añadido canciones a favoritos.</div>
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

  readonly tracks = signal<SpotifyTrack[]>([]);

  constructor() {
    this.library.favorites().subscribe((tracks) => this.tracks.set(tracks));
  }
}
