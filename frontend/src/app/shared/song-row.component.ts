import { Component, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Heart, LucideAngularModule, Play, Plus, Volume2, VolumeX } from 'lucide-angular';

import { AuthService } from '../core/auth.service';
import { LibraryService } from '../core/library.service';
import { formatDuration, SpotifyTrack } from '../core/models';
import { PlayerService } from '../core/player.service';

@Component({
  selector: 'app-song-row',
  imports: [LucideAngularModule, RouterLink],
  template: `
    <div class="song-row">
      <button
        class="icon-button icon-button--play"
        type="button"
        (click)="play()"
        [disabled]="!track().preview_url"
        [title]="track().preview_url ? 'Reproducir preview' : 'Spotify no ofrece preview para esta cancion'"
      >
        <lucide-icon
          [img]="isCurrentSong() && player.isPlaying() ? icons.Volume2 : icons.Play"
          [size]="18"
        ></lucide-icon>
      </button>

      <div class="song-row__index">{{ indexLabel() }}</div>

      <div class="song-row__main">
        <strong>{{ track().name }}</strong>

        @if (showAlbum() && track().album) {
          <a [routerLink]="['/albums', track().album!.id]">
            {{ track().album!.name }}
          </a>
        } @else if (track().artists?.length) {
          <span>{{ track().artists![0].name }}</span>
        }
      </div>

      <span class="song-row__duration">
        {{ duration() }}
      </span>

      @if (track().preview_url) {
        <lucide-icon class="song-row__preview" [img]="icons.Volume2" [size]="16"></lucide-icon>
      } @else {
        <lucide-icon
          class="song-row__preview song-row__preview--missing"
          [img]="icons.VolumeX"
          [size]="16"
          title="Sin preview en Spotify"
        ></lucide-icon>
      }

      <button class="icon-button" type="button" (click)="toggleFavorite()" title="Favorito">
        <lucide-icon
          [class.is-favorite]="isFavorite()"
          [img]="icons.Heart"
          [size]="18"
        ></lucide-icon>
      </button>

      <button
        class="icon-button"
        type="button"
        (click)="showPlaylistMenu.update(v => !v)"
        title="Añadir a playlist"
      >
        <lucide-icon [img]="icons.Plus" [size]="18"></lucide-icon>
      </button>

      @if (showPlaylistMenu()) {
        <div class="playlist-dropdown">
          @for (pl of playlists(); track pl.id) {
            <button
              type="button"
              (click)="addToPlaylist(pl.id)"
              [disabled]="addingLoading()"
            >
              {{ pl.name }}
            </button>
          }
        </div>
      }
    </div>
  `,
})
export class SongRowComponent {
  private readonly auth = inject(AuthService);
  private readonly library = inject(LibraryService);
  readonly player = inject(PlayerService);
  private readonly router = inject(Router);

  readonly track = input.required<SpotifyTrack>();
  readonly index = input<number | null>(null);
  readonly showAlbum = input(false);

  readonly showPlaylistMenu = signal(false);
  readonly addingLoading = signal(false);
  readonly playlists = signal<{ id: number; name: string }[]>([]);
  readonly icons = { Heart, Play, Plus, Volume2, VolumeX };

  constructor() {
    if (this.auth.isLoggedIn()) {
      this.library.playlists().subscribe((items) => {
        this.playlists.set(items.map((p) => ({ id: p.id, name: p.name })));
      });

      this.library.favorites().subscribe();
    }
  }

  indexLabel(): string {
    return this.index() === null ? '' : String(this.index());
  }

  duration(): string {
    return formatDuration(this.track().duration_ms);
  }

  play(): void {
    this.player.play(this.track());
  }

  isCurrentSong(): boolean {
    const current = this.player.currentSong();
    return !!current && current.id === this.track().id;
  }

  toggleFavorite(): void {
    if (!this.auth.isLoggedIn()) {
      void this.router.navigate(['/login']);
      return;
    }

    const isFav = this.isFavorite();

    const request = isFav
      ? this.library.removeFavorite(this.track().id)
      : this.library.addFavorite(this.track().id);

    request.subscribe();
  }

  isFavorite(): boolean {
    return this.library.favoriteTrackIds().includes(this.track().id);
  }

  addToPlaylist(playlistId: number): void {
    if (!this.auth.isLoggedIn() || this.addingLoading()) return;

    this.addingLoading.set(true);

    this.library
      .addTrackToPlaylist(playlistId, this.track().id)
      .subscribe({
        next: () => {
          this.showPlaylistMenu.set(false);
          this.addingLoading.set(false);
        },
        error: () => {
          this.addingLoading.set(false);
        },
      });
  }
}
