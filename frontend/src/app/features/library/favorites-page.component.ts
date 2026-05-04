import { Component, inject, signal } from '@angular/core';

import { LibraryService } from '../../core/library.service';
import { Song } from '../../core/models';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-favorites-page',
  imports: [SongRowComponent],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">Biblioteca</span>
        <h1>Canciones favoritas</h1>
      </div>
      <p>{{ songs().length }} canciones guardadas</p>
    </section>

    <section class="content-section">
      <div class="song-list">
        @for (song of songs(); track song.id; let i = $index) {
          <app-song-row [song]="song" [index]="i + 1" [showAlbum]="true" />
        } @empty {
          <div class="empty-state">Todavia no hay canciones favoritas.</div>
        }
      </div>
    </section>
  `,
})
export class FavoritesPageComponent {
  private readonly library = inject(LibraryService);

  readonly songs = signal<Song[]>([]);

  constructor() {
    this.library.favorites().subscribe((songs) => {
      this.songs.set(songs.map((song) => ({ ...song, isFavorite: true })));
    });
  }
}
