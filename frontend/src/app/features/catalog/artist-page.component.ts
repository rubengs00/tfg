import { CommonModule } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';

import { AuthService } from '../../core/auth.service';
import { CatalogService } from '../../core/catalog.service';
import { LibraryService } from '../../core/library.service';
import { SpotifyAlbum, SpotifyArtist } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';

@Component({
  selector: 'app-artist-page',
  standalone: true,
  imports: [CommonModule, MediaCardComponent],
  template: `
    @if (error()) {
      <div class="empty-state">{{ error() }}</div>
    } @else if (!artist()) {
      <div class="empty-state">Cargando artista...</div>
    } @else {
      <section class="page-hero page-hero--artist">
        <img
          class="page-hero__cover page-hero__cover--round"
          [src]="artist()?.images?.[0]?.url ?? ''"
          [alt]="artist()?.name ?? ''"
        />
        <div>
          <span class="eyebrow">Artista</span>
          <h1>{{ artist()?.name }}</h1>
          <p>
            {{ artist()?.followers?.total ?? 0 | number }} seguidores ·
            {{ artist()?.genres?.[0] ?? 'Artista' }}
          </p>

          <button class="primary-button" type="button" (click)="toggleFollow()">
            {{ isFollowed() ? 'Dejar de seguir' : 'Seguir' }}
          </button>
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Albumes</h2>
          <span>{{ albums().length }} publicados</span>
        </div>

        @if (!albums().length) {
          <div class="empty-state">No hemos encontrado albumes para este artista.</div>
        } @else {
          <div class="media-grid">
            @for (album of albums(); track album.id) {
              <app-media-card
                kind="album"
                [title]="album.name"
                [subtitle]="album.release_date ?? ''"
                [imageUrl]="album.images?.[0]?.url ?? null"
                [route]="['/albums', album.id]"
              />
            }
          </div>
        }
      </section>
    }
  `,
})
export class ArtistPageComponent {
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
