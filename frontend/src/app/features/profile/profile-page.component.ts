import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';

import { AuthService } from '../../core/auth.service';
import { ProfileService } from '../../core/profile.service';
import { ProfileData } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';
import { SongRowComponent } from '../../shared/song-row.component';
import { ProfileEditModalComponent } from './profile-edit-modal.component';

@Component({
  selector: 'app-profile-page',
  standalone: true,
  imports: [CommonModule, MediaCardComponent, SongRowComponent, ProfileEditModalComponent],
  template: `
    <section class="page-hero page-hero--profile">
      <div class="profile-hero">
        <div class="profile-hero__avatar">
          @if (auth.user()?.avatar_url) {
            <img [src]="auth.user()!.avatar_url!" [alt]="auth.user()!.name" />
          } @else {
            <div class="avatar-fallback">{{ auth.user()?.name?.[0] ?? 'U' }}</div>
          }
        </div>

        <div class="profile-hero__meta">
          <span class="eyebrow">Perfil</span>
          <h1>{{ auth.user()?.name ?? 'Perfil' }}</h1>

          @if (data(); as d) {
            <p>
              {{ d.stats.playlists }} playlists ·
              {{ d.stats.favorites }} favoritas ·
              {{ d.stats.followedArtists }} artistas seguidos
            </p>
          }

          <button class="primary-button" type="button" (click)="editing.set(true)">
            Editar perfil
          </button>
        </div>
      </div>
    </section>

    <section class="content-section">
      @if (!data()) {
        <div class="empty-state">Cargando...</div>
      } @else {
        <div class="section-heading">
          <h2>Artistas seguidos</h2>
        </div>

        <div class="media-grid">
          @for (artist of data()!.followedArtists; track artist.id) {
            <app-media-card
              kind="artist"
              [title]="artist.name"
              [subtitle]="artist.genres?.[0] ?? 'Artista'"
              [imageUrl]="artist.images?.[0]?.url ?? null"
              [route]="['/artists', artist.id]"
              [round]="true"
            />
          }
        </div>

        <div class="section-heading section-heading--spaced">
          <h2>Favoritas</h2>
        </div>

        <div class="song-list">
          @for (track of data()!.favoriteTracks; track track.id; let i = $index) {
            <app-song-row [track]="track" [index]="i + 1" [showAlbum]="true" />
          }
        </div>
      }
    </section>

    @if (editing()) {
      <app-profile-edit-modal (closed)="editing.set(false)" />
    }
  `,
})
export class ProfilePageComponent {
  readonly auth = inject(AuthService);
  private readonly profile = inject(ProfileService);

  readonly data = signal<ProfileData | null>(null);
  readonly editing = signal(false);

  constructor() {
    this.profile.profile().subscribe((profile) => this.data.set(profile));
  }
}
