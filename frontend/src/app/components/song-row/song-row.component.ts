import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Heart, LucideAngularModule, Play, Plus, Volume2, VolumeX } from 'lucide-angular';

import { AuthService } from '../../core/auth.service';
import { LibraryService } from '../../core/library.service';
import { formatDuration, SpotifyTrack } from '../../core/models';
import { PlayerService } from '../../core/player.service';

@Component({
  selector: 'app-song-row',
  imports: [LucideAngularModule, RouterLink],
  templateUrl: './song-row.component.html',
  styleUrl: './song-row.component.scss',
})
export class SongRowComponent {
  private readonly auth = inject(AuthService);
  private readonly library = inject(LibraryService);
  readonly player = inject(PlayerService);
  private readonly router = inject(Router);

  readonly track = input.required<SpotifyTrack>();
  readonly index = input<number | null>(null);
  readonly showAlbum = input(false);
  readonly showActions = input(true);

  readonly showPlaylistMenu = signal(false);
  readonly addingLoading = signal(false);
  readonly playlists = signal<{ id: number; name: string }[]>([]);
  readonly icons = { Heart, Play, Plus, Volume2, VolumeX };

  readonly isFavorite = computed(() => this.library.favoriteTrackIds().includes(this.track().id));

  constructor() {
    effect(() => {
      if (!this.auth.isLoggedIn() || !this.showActions()) {
        this.playlists.set([]);
        return;
      }

      this.library.playlists().subscribe((items) => {
        this.playlists.set(items.map((p) => ({ id: p.id, name: p.name })));
      });
    });
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
      : this.library.addFavorite(this.track().id, this.track());

    request.subscribe();
  }

  addToPlaylist(playlistId: number): void {
    if (!this.auth.isLoggedIn()) {
      void this.router.navigate(['/login']);
      return;
    }

    if (this.addingLoading()) return;

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



