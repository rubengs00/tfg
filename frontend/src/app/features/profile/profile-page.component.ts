import { Component, inject, signal } from '@angular/core';

import { ProfileData } from '../../core/models';
import { ProfileService } from '../../core/profile.service';
import { MediaCardComponent } from '../../shared/media-card.component';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-profile-page',
  imports: [MediaCardComponent, SongRowComponent],
  template: `
    @if (profile(); as data) {
      <section class="detail-hero">
        <img class="detail-hero__image detail-hero__image--avatar" [src]="data.user.avatarUrl ?? ''" [alt]="data.user.name" />
        <div>
          <span class="eyebrow">Perfil</span>
          <h1>{{ data.user.name }}</h1>
          <p>
            Playlists: {{ data.stats.playlists }} / Favoritos: {{ data.stats.favorites }} /
            Artistas seguidos: {{ data.stats.followedArtists }}
          </p>
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Artistas seguidos</h2>
        </div>
        <div class="media-grid">
          @for (artist of data.followedArtists; track artist.id) {
            <app-media-card
              [title]="artist.name"
              [subtitle]="artist.genre ?? 'Artista'"
              [imageUrl]="artist.imageUrl"
              [route]="['/artists', artist.id]"
              kind="artist"
              [round]="true"
            />
          }
        </div>
      </section>

      <section class="content-section">
        <div class="section-heading">
          <h2>Ultimos favoritos</h2>
        </div>
        <div class="song-list">
          @for (song of data.favoriteSongs; track song.id; let i = $index) {
            <app-song-row [song]="song" [index]="i + 1" [showAlbum]="true" />
          }
        </div>
      </section>
    } @else {
      <div class="empty-state">Cargando perfil...</div>
    }
  `,
})
export class ProfilePageComponent {
  private readonly profileService = inject(ProfileService);

  readonly profile = signal<ProfileData | null>(null);

  constructor() {
    this.profileService.profile().subscribe((profile) => this.profile.set(profile));
  }
}
