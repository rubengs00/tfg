import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Pencil, Plus, Trash2 } from 'lucide-angular';

import { LibraryService } from '../../core/library.service';
import { Playlist, SpotifyTrack } from '../../core/models';
import { MediaCardComponent } from '../../shared/media-card.component';
import { SongRowComponent } from '../../shared/song-row.component';
import { PlaylistEditModalComponent } from './playlist-edit-modal.component';

@Component({
  selector: 'app-playlists-page',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    LucideAngularModule,
    MediaCardComponent,
    SongRowComponent,
    PlaylistEditModalComponent,
  ],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">Biblioteca</span>
        <h1>Mis playlists</h1>
      </div>

      <form class="inline-form" (ngSubmit)="createPlaylist()">
        <input
          name="playlistName"
          [(ngModel)]="playlistName"
          placeholder="Nueva playlist"
          required
        />
        <button class="icon-button icon-button--primary" type="submit">
          <lucide-icon [img]="icons.Plus" [size]="18"></lucide-icon>
        </button>
      </form>
    </section>

    <section class="content-section">
      @if (!playlists().length) {
        <div class="empty-state">Todavía no has creado ninguna playlist.</div>
      } @else {
        <div class="media-grid">
          @for (playlist of playlists(); track playlist.id) {
            <div class="playlist-card">
              <button class="plain-card" type="button" (click)="selectPlaylist(playlist)">
                <app-media-card
                  [title]="playlist.name"
                  [subtitle]="(playlist.songsCount ?? 0) + ' canciones'"
                  [imageUrl]="playlist.coverUrl ?? null"
                  [route]="[]"
                  kind="playlist"
                />
              </button>

              <div class="playlist-actions">
                <button
                  class="icon-button"
                  type="button"
                  (click)="openEdit(playlist)"
                  title="Editar playlist"
                >
                  <lucide-icon [img]="icons.Pencil" [size]="16"></lucide-icon>
                </button>

                <button
                  class="icon-button icon-button--danger"
                  type="button"
                  (click)="deletePlaylist(playlist)"
                  title="Eliminar playlist"
                >
                  <lucide-icon [img]="icons.Trash2" [size]="16"></lucide-icon>
                </button>
              </div>
            </div>
          }
        </div>
      }
    </section>

    @if (selected(); as sel) {
      <section class="content-section">
        <div class="section-heading">
          <h2>{{ sel.playlist.name }}</h2>
          <span>{{ sel.tracks.length }} canciones</span>
        </div>

        <div class="song-list">
          @for (track of sel.tracks; track track.id; let i = $index) {
            <app-song-row [track]="track" [index]="i + 1" [showAlbum]="true" />
          }
        </div>
      </section>
    }

    @if (editingPlaylist(); as p) {
      <app-playlist-edit-modal
        [playlist]="p"
        (closed)="editingPlaylist.set(null)"
        (updated)="handleUpdated($event)"
      />
    }
  `,
})
export class PlaylistsPageComponent {
  private readonly library = inject(LibraryService);

  playlistName = '';

  readonly playlists = signal<Playlist[]>([]);
  readonly selected = signal<{ playlist: Playlist; tracks: SpotifyTrack[] } | null>(null);
  readonly editingPlaylist = signal<Playlist | null>(null);

  readonly icons = { Plus, Trash2, Pencil };

  constructor() {
    this.loadPlaylists();
  }

  selectPlaylist(playlist: Playlist): void {
    this.library.playlist(playlist.id).subscribe((full) => {
      this.selected.set(full);
    });
  }

  createPlaylist(): void {
    const name = this.playlistName.trim();
    if (!name) return;

    this.library.createPlaylist(name).subscribe(({ playlist }) => {
      this.playlists.update((items) => [playlist, ...items]);
      this.playlistName = '';
    });
  }

  openEdit(playlist: Playlist) {
    this.editingPlaylist.set(playlist);
  }

  deletePlaylist(playlist: Playlist): void {
    this.playlists.update((items) => items.filter((p) => p.id !== playlist.id));
    const selected = this.selected();
    if (selected?.playlist.id === playlist.id) {
      this.selected.set(null);
    }
    this.library.deletePlaylist(playlist.id).subscribe();
  }

  private loadPlaylists(): void {
    this.library.playlists().subscribe((playlists) => {
      this.playlists.set(playlists);
    });
  }

  handleUpdated(event: { playlist: Playlist; coverFile: File | null }): void {
    const { playlist, coverFile } = event;

    const formData = new FormData();
    formData.append('name', playlist.name);
    formData.append('description', playlist.description ?? '');
    if (coverFile) {
      formData.append('cover', coverFile);
    }

    this.library.updatePlaylist(playlist.id, formData).subscribe((updated) => {
      // actualizar grid
      this.playlists.update((items) => items.map((p) => (p.id === updated.id ? updated : p)));

      // actualizar playlist seleccionada si está abierta
      const selected = this.selected();
      if (selected?.playlist.id === updated.id) {
        this.selected.set({ ...selected, playlist: updated });
      }

      this.editingPlaylist.set(null);
    });
  }
}
