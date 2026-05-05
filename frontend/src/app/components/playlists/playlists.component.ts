import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Music2, Pencil, Plus, Trash2 } from 'lucide-angular';

import { LibraryService } from '../../services/library.service';
import { Playlist, PlaylistDetail, SpotifyTrack } from '../../interfaces/music.interfaces';
import { PlaylistDetailModalComponent } from '../playlist-detail-modal/playlist-detail-modal.component';
import { PlaylistEditModalComponent } from '../playlist-edit-modal/playlist-edit-modal.component';

@Component({
  selector: 'app-playlists',
  standalone: true,
  imports: [
    FormsModule,
    LucideAngularModule,
    PlaylistDetailModalComponent,
    PlaylistEditModalComponent,
  ],
  templateUrl: './playlists.component.html',
  styleUrl: './playlists.component.scss',
})
export class PlaylistsComponent {
  private readonly library = inject(LibraryService);

  playlistName = '';

  readonly playlists = signal<Playlist[]>([]);
  readonly selected = signal<PlaylistDetail | null>(null);
  readonly editingPlaylist = signal<Playlist | null>(null);
  readonly loading = signal(true);
  readonly loadingPlaylistId = signal<number | null>(null);
  readonly detailError = signal<string | null>(null);

  readonly icons = { Music2, Plus, Trash2, Pencil };

  constructor() {
    this.loadPlaylists();
  }

  openPlaylist(playlist: Playlist): void {
    if (this.loadingPlaylistId()) return;

    this.loadingPlaylistId.set(playlist.id);
    this.detailError.set(null);

    this.library.playlist(playlist.id).subscribe({
      next: (detail) => {
        this.selected.set(detail);
        this.loadingPlaylistId.set(null);
        this.replacePlaylist(detail.playlist);
      },
      error: () => {
        this.loadingPlaylistId.set(null);
        this.detailError.set('No se pudo cargar la playlist.');
      },
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

  openEdit(playlist: Playlist): void {
    this.editingPlaylist.set(playlist);
  }

  deletePlaylist(playlist: Playlist): void {
    const previous = this.playlists();

    this.playlists.update((items) => items.filter((p) => p.id !== playlist.id));
    if (this.selected()?.playlist.id === playlist.id) {
      this.selected.set(null);
    }

    this.library.deletePlaylist(playlist.id).subscribe({
      error: () => this.playlists.set(previous),
    });
  }

  removeTrackFromSelected(track: SpotifyTrack): void {
    const current = this.selected();
    if (!current) return;

    const nextTracks = current.tracks.filter((item) => item.id !== track.id);
    this.selected.set({ ...current, tracks: nextTracks });

    this.library.removeTrackFromPlaylist(current.playlist.id, track.id).subscribe({
      next: ({ playlist }) => {
        this.replacePlaylist(playlist);
        this.selected.update((detail) =>
          detail?.playlist.id === playlist.id ? { ...detail, playlist } : detail,
        );
      },
      error: () => this.selected.set(current),
    });
  }

  handleUpdated(playlist: Playlist): void {
    this.replacePlaylist(playlist);
    this.selected.update((detail) =>
      detail?.playlist.id === playlist.id ? { ...detail, playlist } : detail,
    );
    this.editingPlaylist.set(null);
  }

  private loadPlaylists(): void {
    this.library.playlists().subscribe({
      next: (playlists) => {
        this.playlists.set(playlists);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  private replacePlaylist(playlist: Playlist): void {
    this.playlists.update((items) =>
      items.map((item) => (item.id === playlist.id ? playlist : item)),
    );
  }
}
