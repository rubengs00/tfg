import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { Check, LucideAngularModule, UserPlus } from 'lucide-angular';

import { AuthService } from '../../core/auth.service';
import { CatalogService } from '../../core/catalog.service';
import { LibraryService } from '../../core/library.service';
import { Artist } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';

@Component({
  selector: 'app-artist-page',
  imports: [LucideAngularModule, MediaCardComponent],
  template: `
    @if (artist(); as currentArtist) {
      <section class="detail-hero">
        <img class="detail-hero__image detail-hero__image--round" [src]="currentArtist.imageUrl ?? ''" [alt]="currentArtist.name" />
        <div>
          <span class="eyebrow">Artista</span>
          <h1>{{ currentArtist.name }}</h1>
          <p>{{ currentArtist.genre }} / {{ currentArtist.followers.toLocaleString('es-ES') }} seguidores</p>
          <button class="primary-button" type="button" (click)="toggleFollow()">
            <lucide-icon [img]="following() ? icons.Check : icons.UserPlus" [size]="18"></lucide-icon>
            {{ following() ? 'Siguiendo' : 'Seguir' }}
          </button>
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Albumes</h2>
        </div>
        <div class="media-grid">
          @for (album of currentArtist.albums ?? []; track album.id) {
            <app-media-card
              [title]="album.title"
              [subtitle]="album.releaseYear?.toString() ?? 'Album'"
              [imageUrl]="album.coverUrl"
              [route]="['/albums', album.id]"
            />
          }
        </div>
      </section>
    } @else {
      <div class="empty-state">Cargando artista...</div>
    }
  `,
})
export class ArtistPageComponent {
  private readonly auth = inject(AuthService);
  private readonly catalog = inject(CatalogService);
  private readonly library = inject(LibraryService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly artist = signal<Artist | null>(null);
  readonly following = signal(false);
  readonly icons = { Check, UserPlus };

  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      const id = Number(params.get('id'));
      this.catalog.artist(id).subscribe((artist) => {
        this.artist.set(artist);
        this.following.set(Boolean(artist.isFollowed));
      });
    });
  }

  toggleFollow(): void {
    const artist = this.artist();
    if (!artist) {
      return;
    }

    if (!this.auth.isLoggedIn()) {
      void this.router.navigate(['/login']);
      return;
    }

    const request = this.following()
      ? this.library.unfollowArtist(artist.id)
      : this.library.followArtist(artist.id);

    request.subscribe(() => this.following.update((value) => !value));
  }
}
