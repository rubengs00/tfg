import { DecimalPipe } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';

import { AuthService } from '../../services/auth.service';
import { CatalogService } from '../../services/catalog.service';
import { LibraryService } from '../../services/library.service';
import { SpotifyAlbum, SpotifyArtist } from '../../interfaces/music.interfaces';
import { MediaCardComponent } from '../media-card/media-card.component';

@Component({
  selector: 'app-artist',
  standalone: true,
  imports: [DecimalPipe, MediaCardComponent],
  templateUrl: './artist.component.html',
  styleUrl: './artist.component.scss',
})
export class ArtistComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly auth = inject(AuthService);
  private readonly catalog = inject(CatalogService);
  private readonly library = inject(LibraryService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly router = inject(Router);

  readonly artist = signal<SpotifyArtist | null>(null);
  readonly albums = signal<SpotifyAlbum[]>([]);
  readonly isFollowed = signal(false);
  readonly error = signal('');

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = params.get('id');
      if (!id) return;

      this.error.set('');
      this.artist.set(null);
      this.albums.set([]);

      this.catalog.artist(id).subscribe({
        next: (response) => {
          if (!response.artist) {
            this.error.set('No se ha podido cargar el artista.');
            return;
          }

          const totalFollowers = response.artist.followers?.total ?? 0;
          this.artist.set({
            ...response.artist,
            followers: { total: totalFollowers },
          });
          this.albums.set(response.albums ?? []);

          const followed = this.library.followedArtistIds().includes(response.artist.id);
          this.isFollowed.set(followed);
        },
        error: () => this.error.set('No se ha podido cargar el artista.'),
      });
    });
  }

  toggleFollow(): void {
    const currentArtist = this.artist();
    if (!currentArtist) return;

    if (!this.auth.isLoggedIn()) {
      void this.router.navigate(['/login']);
      return;
    }

    const followed = this.isFollowed();
    const request = followed
      ? this.library.unfollowArtist(currentArtist.id)
      : this.library.followArtist(currentArtist.id);

    request.subscribe({
      next: () => {
        this.isFollowed.set(!followed);
        this.artist.update((artist) => {
          if (!artist) return artist;

          const currentFollowers = artist.followers?.total ?? 0;
          const nextFollowers = followed
            ? Math.max(0, currentFollowers - 1)
            : currentFollowers + 1;

          return {
            ...artist,
            followers: { total: nextFollowers },
          };
        });
      },
    });
  }
}
