import { CommonModule } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { CatalogService } from '../../core/catalog.service';
import { LibraryService } from '../../core/library.service';
import { SpotifyAlbum, SpotifyArtist } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';

@Component({
  selector: 'app-artist-page',
  standalone: true,
  imports: [CommonModule, MediaCardComponent],
  template: `
    @if (!artist()) {
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
          <h2>Álbumes</h2>
        </div>

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
      </section>
    }
  `,
})
export class ArtistPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly catalog = inject(CatalogService);
  private readonly library = inject(LibraryService);
  private readonly destroyRef = inject(DestroyRef);

  readonly artist = signal<SpotifyArtist | null>(null);
  readonly albums = signal<SpotifyAlbum[]>([]);
  readonly isFollowed = signal(false);

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = params.get('id');
      if (!id) return;

      // CatalogService espera ID de Spotify como string
      this.catalog.artist(id).subscribe((response) => {
        this.artist.set(response.artist);
        this.albums.set(response.albums);

        const followed = this.library.followedArtistIds().includes(response.artist.id);
        this.isFollowed.set(followed);
      });
    });
  }

  toggleFollow() {
    const artist = this.artist();
    if (!artist) return;

    const isFollowed = this.isFollowed();
    const req = isFollowed ? this.library.unfollowArtist(artist.id) : this.library.followArtist(artist.id);

    req.subscribe(() => this.isFollowed.set(!isFollowed));
  }
}
