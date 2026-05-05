import { Component, inject, signal } from '@angular/core';

import { LibraryService } from '../../services/library.service';
import { SongRowComponent } from '../song-row/song-row.component';

@Component({
  selector: 'app-favorites',
  standalone: true,
  imports: [SongRowComponent],
  templateUrl: './favorites.component.html',
  styleUrl: './favorites.component.scss',
})
export class FavoritesComponent {
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
