import { Component, effect, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Heart, LucideAngularModule, Play, Plus, Volume2, VolumeX } from 'lucide-angular';

import { AuthService } from '../core/auth.service';
import { LibraryService } from '../core/library.service';
import { formatDuration, Song } from '../core/models';
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
        [disabled]="!song().previewUrl"
        [title]="song().previewUrl ? 'Reproducir preview' : 'Spotify no ofrece preview para esta cancion'"
      >
        <lucide-icon [img]="icons.Play" [size]="18"></lucide-icon>
      </button>

      <div class="song-row__index">{{ indexLabel() }}</div>

      <div class="song-row__main">
        <strong>{{ song().title }}</strong>
        @if (showAlbum() && song().album) {
          <a [routerLink]="['/albums', song().album!.id]">{{ song().album!.title }}</a>
        } @else if (song().album?.artist) {
          <span>{{ song().album!.artist!.name }}</span>
        }
      </div>

      <span class="song-row__duration">{{ duration() }}</span>

      @if (song().previewUrl) {
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
        <lucide-icon [class.is-favorite]="favorite()" [img]="icons.Heart" [size]="18"></lucide-icon>
      </button>
    </div>
  `,
})
export class SongRowComponent {
  private readonly auth = inject(AuthService);
  private readonly library = inject(LibraryService);
  private readonly player = inject(PlayerService);
  private readonly router = inject(Router);

  readonly song = input.required<Song>();
  readonly index = input<number | null>(null);
  readonly showAlbum = input(false);
  readonly favorite = signal(false);
  readonly icons = { Heart, Play, Plus, Volume2, VolumeX };

  constructor() {
    effect(() => this.favorite.set(Boolean(this.song().isFavorite)));
  }

  indexLabel(): string {
    return this.index() === null ? '' : String(this.index());
  }

  duration(): string {
    return formatDuration(this.song().durationSeconds);
  }

  play(): void {
    this.player.play(this.song());
  }

  toggleFavorite(): void {
    if (!this.auth.isLoggedIn()) {
      void this.router.navigate(['/login']);
      return;
    }

    const request = this.favorite()
      ? this.library.removeFavorite(this.song().id)
      : this.library.addFavorite(this.song().id);

    request.subscribe(() => this.favorite.update((value) => !value));
  }
}
