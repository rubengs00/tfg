import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Plus, Trash2 } from 'lucide-angular';

import { LibraryService } from '../../core/library.service';
import { Playlist } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-playlists-page',
  imports: [FormsModule, LucideAngularModule, MediaCardComponent, SongRowComponent],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">Biblioteca</span>
        <h1>Mis playlists</h1>
      </div>
      <form class="inline-form" (ngSubmit)="createPlaylist()">
        <input name="playlistName" [(ngModel)]="playlistName" placeholder="Nueva playlist" required />
        <button class="icon-button icon-button--primary" type="submit" title="Crear playlist">
          <lucide-icon [img]="icons.Plus" [size]="18"></lucide-icon>
        </button>
      </form>
    </section>

    <section class="content-section">
      <div class="media-grid">
        @for (playlist of playlists(); track playlist.id) {
          <button class="plain-card" type="button" (click)="selectPlaylist(playlist)">
            <app-media-card
              [title]="playlist.name"
              [subtitle]="playlist.songsCount + ' canciones'"
              [imageUrl]="playlist.coverUrl"
              [route]="['/playlists']"
              kind="playlist"
            />
          </button>
        } @empty {
          <div class="empty-state">Crea tu primera playlist.</div>
        }
      </div>
    </section>

    @if (selected(); as playlist) {
      <section class="content-section playlist-detail">
        <div class="section-heading">
          <h2>{{ playlist.name }}</h2>
          <span>{{ playlist.songs?.length ?? 0 }} canciones</span>
        </div>
        <div class="song-list">
          @for (song of playlist.songs ?? []; track song.id; let i = $index) {
            <div class="song-row song-row--with-action">
              <app-song-row [song]="song" [index]="i + 1" [showAlbum]="true" />
              <button class="icon-button" type="button" (click)="removeSong(playlist, song.id)" title="Quitar">
                <lucide-icon [img]="icons.Trash2" [size]="17"></lucide-icon>
              </button>
            </div>
          } @empty {
            <div class="empty-state">Esta playlist esta vacia.</div>
          }
        </div>
      </section>
    }
  `,
})
export class PlaylistsPageComponent {
  private readonly library = inject(LibraryService);

  playlistName = '';

  readonly playlists = signal<Playlist[]>([]);
  readonly selected = signal<Playlist | null>(null);
  readonly icons = { Plus, Trash2 };

  constructor() {
    this.loadPlaylists();
  }

  selectPlaylist(playlist: Playlist): void {
    this.library.playlist(playlist.id).subscribe((fullPlaylist) => this.selected.set(fullPlaylist));
  }

  createPlaylist(): void {
    const name = this.playlistName.trim();
    if (!name) {
      return;
    }

    this.library.createPlaylist(name).subscribe(({ playlist }) => {
      this.playlists.update((items) => [playlist, ...items]);
      this.playlistName = '';
      this.selectPlaylist(playlist);
    });
  }

  removeSong(playlist: Playlist, songId: number): void {
    this.library.removeSong(playlist.id, songId).subscribe((updated) => this.selected.set(updated));
  }

  private loadPlaylists(): void {
    this.library.playlists().subscribe((playlists) => {
      this.playlists.set(playlists);
      if (playlists[0]) {
        this.selectPlaylist(playlists[0]);
      }
    });
  }
}
