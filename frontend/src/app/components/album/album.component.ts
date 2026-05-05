import { CommonModule } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';

import { CatalogService } from '../../core/catalog.service';
import { SpotifyAlbum, SpotifyTrack } from '../../core/models';
import { SongRowComponent } from '../song-row/song-row.component';

@Component({
  selector: 'app-album',
  standalone: true,
  imports: [CommonModule, SongRowComponent],
  templateUrl: './album.component.html',
  styleUrl: './album.component.scss',
})
export class AlbumComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly destroyRef = inject(DestroyRef);

  readonly album = signal<SpotifyAlbum | null>(null);
  readonly tracks = signal<SpotifyTrack[]>([]);
  readonly error = signal('');

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = params.get('id');
      if (!id) return;

      this.error.set('');
      this.album.set(null);
      this.tracks.set([]);

      this.catalog.album(id).subscribe((response) => {
        if (!response.album) {
          this.error.set('No se ha podido cargar el album.');
          return;
        }

        this.album.set(response.album);
        this.tracks.set(response.tracks ?? []);
      });
    });
  }
}



