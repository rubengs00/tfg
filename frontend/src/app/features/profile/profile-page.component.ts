import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';

import { AuthService } from '../../core/auth.service';
import { LibraryService } from '../../core/library.service';
import { ProfileData } from '../../core/models';
import { ProfileService } from '../../core/profile.service';
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
          @if (auth.user()?.avatarUrl) {
            <img [src]="auth.user()!.avatarUrl!" [alt]="auth.user()!.name" />
          } @else {
            <div class="avatar-fallback">{{ auth.user()?.name?.[0] ?? 'U' }}</div>
          }
        </div>

        <div class="profile-hero__meta">
          <span class="eyebrow">Perfil</span>
          <h1>{{ auth.user()?.name ?? 'Perfil' }}</h1>

          @if (data(); as d) {
            <p>
              {{ d.playlists.length }} playlists ·
              {{ favoriteTracks().length }} favoritas ·
              {{ followedArtists().length }} artistas seguidos
            </p>
          }

          <button class="primary-button" type="button" (click)="editing.set(true)">
            Editar perfil
          </button>
        </div>
      </div>
    </section>

    <section class="content-section">
      @if (loading()) {
        <div class="empty-state">Cargando perfil...</div>
      } @else if (!data()) {
        <div class="empty-state">No se ha podido cargar tu perfil.</div>
      } @else {
        <div class="section-heading">
          <h2>Artistas seguidos</h2>
          <span>{{ followedArtists().length }} guardados</span>
        </div>

        @if (!followedArtists().length) {
          <div class="empty-state">Cuando sigas artistas apareceran aqui y podras entrar a su detalle.</div>
        } @else {
          <div class="media-grid">
            @for (artist of followedArtists(); track artist.id) {
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
        }

        <div class="section-heading section-heading--spaced">
          <h2>Favoritas</h2>
          <span>{{ favoriteTracks().length }} canciones</span>
        </div>

        @if (!favoriteTracks().length) {
          <div class="empty-state">Tus favoritas apareceran aqui automaticamente.</div>
        } @else {
          <div class="song-list">
            @for (track of favoriteTracks(); track track.id; let i = $index) {
              <app-song-row [track]="track" [index]="i + 1" [showAlbum]="true" />
            }
          </div>
        }
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
  private readonly library = inject(LibraryService);

  readonly data = signal<ProfileData | null>(null);
  readonly loading = signal(true);
  readonly editing = signal(false);
  readonly favoriteTracks = computed(() => this.library.favoriteTracks());
  readonly followedArtists = computed(() => this.library.followedArtistsStateView());

  constructor() {
    this.profile.profile().subscribe({
      next: (profile) => {
        this.data.set(profile);
        this.library.hydrateFavoriteTracks(profile.favoriteTracks ?? []);
        this.library.hydrateFollowedArtists(profile.followedArtists ?? []);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });

    this.library.favorites().subscribe();
    this.library.followedArtists().subscribe();
  }
}
