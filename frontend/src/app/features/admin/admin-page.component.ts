import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  Activity,
  Eye,
  LucideAngularModule,
  Music4,
  Plus,
  Shield,
  Trash2,
  UserRoundCheck,
  UserRoundX,
} from 'lucide-angular';

import { AdminService } from '../../core/admin.service';
import {
  ActivityEvent,
  AdminStatsResponse,
  AdminUserLibrary,
  Playlist,
  PlaylistDetail,
  User,
} from '../../core/models';
import { SongRowComponent } from '../../shared/song-row.component';

@Component({
  selector: 'app-admin-page',
  imports: [CommonModule, FormsModule, LucideAngularModule, SongRowComponent],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Panel de control</h1>
      </div>
      <p>Gestiona usuarios, actividad y la biblioteca guardada en MusicHub.</p>
    </section>

    @if (stats(); as stats) {
      <section class="stats-grid stats-grid--dense">
        <div><span>Usuarios</span><strong>{{ stats.totals.users }}</strong></div>
        <div><span>Artistas locales</span><strong>{{ stats.totals.artists }}</strong></div>
        <div><span>Canciones locales</span><strong>{{ stats.totals.songs }}</strong></div>
        <div><span>Playlists</span><strong>{{ stats.totals.playlists }}</strong></div>
        <div><span>Eventos</span><strong>{{ stats.totals.activityEvents }}</strong></div>
      </section>
    }

    <section class="admin-grid admin-grid--balanced">
      <div class="admin-panel">
        <div class="section-heading">
          <h2>Usuarios</h2>
          <span>{{ users().length }} cargados</span>
        </div>

        <form class="admin-form" (ngSubmit)="createUser()">
          <input name="name" [(ngModel)]="newUser.name" placeholder="Nombre" required />
          <input name="email" [(ngModel)]="newUser.email" placeholder="Email" required />
          <input name="password" type="password" [(ngModel)]="newUser.password" placeholder="Password" required />
          <select name="role" [(ngModel)]="newUser.role">
            <option value="user">user</option>
            <option value="admin">admin</option>
          </select>
          <button class="icon-button icon-button--primary" type="submit" title="Crear usuario">
            <lucide-icon [img]="icons.Plus" [size]="18"></lucide-icon>
          </button>
        </form>

        <div class="admin-table">
          @for (user of users(); track user.id) {
            <div class="admin-table__row admin-table__row--stacked">
              <div>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }}</span>
                <span class="admin-meta">
                  {{ user.favoriteSongsCount ?? 0 }} favoritas ·
                  {{ user.followedArtistsCount ?? 0 }} artistas ·
                  {{ user.playlistsCount ?? 0 }} playlists
                </span>
              </div>

              <div class="admin-row-actions">
                <select [ngModel]="user.role" (ngModelChange)="setRole(user, $event)">
                  <option value="user">user</option>
                  <option value="admin">admin</option>
                </select>

                <button
                  class="icon-button"
                  type="button"
                  (click)="toggleActive(user)"
                  [title]="user.isActive ? 'Desactivar usuario' : 'Activar usuario'"
                >
                  <lucide-icon [img]="user.isActive ? icons.UserRoundX : icons.UserRoundCheck" [size]="17"></lucide-icon>
                </button>

                <button class="icon-button" type="button" (click)="inspectUser(user)" title="Ver biblioteca">
                  <lucide-icon [img]="icons.Eye" [size]="17"></lucide-icon>
                </button>

                <button class="icon-button" type="button" (click)="deleteUser(user)" title="Eliminar usuario">
                  <lucide-icon [img]="icons.Trash2" [size]="17"></lucide-icon>
                </button>
              </div>
            </div>
          }
        </div>
      </div>

      <div class="admin-panel">
        <div class="section-heading">
          <h2>Resumen</h2>
          <lucide-icon [img]="icons.Shield" [size]="18"></lucide-icon>
        </div>

        <div class="compact-list">
          <div>
            <strong>Top artistas</strong>
            <span>Segun seguimiento de usuarios</span>
          </div>
          @for (artist of stats()?.topArtists ?? []; track artist.id) {
            <div>
              <strong>{{ artist.name }}</strong>
              <span>{{ artist.followers.toLocaleString('es-ES') }} seguidores</span>
            </div>
          }
        </div>

        <div class="section-heading section-heading--spaced">
          <h2>Top canciones</h2>
          <lucide-icon [img]="icons.Music4" [size]="18"></lucide-icon>
        </div>
        <div class="compact-list">
          @for (song of stats()?.topSongs ?? []; track song.id) {
            <div>
              <strong>{{ song.title }}</strong>
              <span>{{ song.album?.artist?.name ?? 'Sin album local' }}</span>
            </div>
          }
        </div>
      </div>
    </section>

    <section class="content-section">
      <div class="section-heading">
        <h2>Actividad reciente</h2>
        <lucide-icon [img]="icons.Activity" [size]="18"></lucide-icon>
      </div>
      <div class="activity-list">
        @for (event of activity(); track event.id) {
          <div>
            <strong>{{ event.action }}</strong>
            <span>{{ event.user?.name ?? 'Sistema' }} · {{ event.resourceType }} · {{ shortDate(event.createdAt) }}</span>
          </div>
        }
      </div>
    </section>

    <section class="content-section">
      <div class="section-heading">
        <h2>Biblioteca de usuario</h2>
        @if (selectedUser()) {
          <span>{{ selectedUser()!.name }}</span>
        }
      </div>

      @if (loadingLibrary()) {
        <div class="empty-state">Cargando biblioteca del usuario...</div>
      } @else if (!selectedLibrary()) {
        <div class="empty-state">Selecciona un usuario para revisar sus favoritos, artistas seguidos y playlists.</div>
      } @else {
        <section class="stats-grid stats-grid--compact">
          <div><span>Favoritas</span><strong>{{ selectedLibrary()!.stats.favorites }}</strong></div>
          <div><span>Artistas</span><strong>{{ selectedLibrary()!.stats.followedArtists }}</strong></div>
          <div><span>Playlists</span><strong>{{ selectedLibrary()!.stats.playlists }}</strong></div>
        </section>

        <section class="admin-library-grid">
          <div class="admin-panel">
            <div class="section-heading">
              <h2>Favoritas</h2>
            </div>

            @if (!selectedLibrary()!.favoriteTracks.length) {
              <div class="empty-state">Este usuario no tiene favoritas guardadas.</div>
            } @else {
              <div class="song-list">
                @for (track of selectedLibrary()!.favoriteTracks; track track.id; let i = $index) {
                  <div class="admin-song-row">
                    <app-song-row [track]="track" [index]="i + 1" [showAlbum]="true" />
                    <button
                      class="icon-button"
                      type="button"
                      (click)="removeFavorite(track.id)"
                      title="Quitar de favoritos"
                    >
                      <lucide-icon [img]="icons.Trash2" [size]="16"></lucide-icon>
                    </button>
                  </div>
                }
              </div>
            }
          </div>

          <div class="admin-panel">
            <div class="section-heading">
              <h2>Artistas seguidos</h2>
            </div>

            @if (!selectedLibrary()!.followedArtists.length) {
              <div class="empty-state">Este usuario no sigue artistas todavia.</div>
            } @else {
              <div class="compact-list">
                @for (artist of selectedLibrary()!.followedArtists; track artist.id) {
                  <div>
                    <div>
                      <strong>{{ artist.name }}</strong>
                      <span>{{ artist.genres?.[0] ?? 'Artista' }}</span>
                    </div>
                    <button
                      class="icon-button"
                      type="button"
                      (click)="removeFollowedArtist(artist.id)"
                      title="Quitar seguimiento"
                    >
                      <lucide-icon [img]="icons.Trash2" [size]="16"></lucide-icon>
                    </button>
                  </div>
                }
              </div>
            }
          </div>
        </section>

        <section class="content-section">
          <div class="section-heading">
            <h2>Playlists</h2>
          </div>

          @if (!selectedLibrary()!.playlists.length) {
            <div class="empty-state">Este usuario no tiene playlists creadas.</div>
          } @else {
            <div class="compact-list">
              @for (playlist of selectedLibrary()!.playlists; track playlist.id) {
                <div class="admin-playlist-row">
                  <div>
                    <strong>{{ playlist.name }}</strong>
                    <span>{{ playlist.songsCount ?? 0 }} canciones</span>
                  </div>

                  <div class="admin-row-actions">
                    <button class="icon-button" type="button" (click)="viewPlaylist(playlist)" title="Ver pistas">
                      <lucide-icon [img]="icons.Eye" [size]="16"></lucide-icon>
                    </button>
                    <button class="icon-button" type="button" (click)="removePlaylist(playlist)" title="Eliminar playlist">
                      <lucide-icon [img]="icons.Trash2" [size]="16"></lucide-icon>
                    </button>
                  </div>
                </div>
              }
            </div>
          }
        </section>

        @if (selectedPlaylistDetail(); as detail) {
          <section class="content-section">
            <div class="section-heading">
              <h2>{{ detail.playlist.name }}</h2>
              <span>{{ detail.tracks.length }} canciones</span>
            </div>

            @if (!detail.tracks.length) {
              <div class="empty-state">Esta playlist no tiene canciones.</div>
            } @else {
              <div class="song-list">
                @for (track of detail.tracks; track track.id; let i = $index) {
                  <app-song-row [track]="track" [index]="i + 1" [showAlbum]="true" />
                }
              </div>
            }
          </section>
        }
      }
    </section>
  `,
})
export class AdminPageComponent {
  private readonly admin = inject(AdminService);

  readonly stats = signal<AdminStatsResponse | null>(null);
  readonly users = signal<User[]>([]);
  readonly activity = signal<ActivityEvent[]>([]);
  readonly selectedUser = signal<User | null>(null);
  readonly selectedLibrary = signal<AdminUserLibrary | null>(null);
  readonly selectedPlaylistDetail = signal<PlaylistDetail | null>(null);
  readonly loadingLibrary = signal(false);

  readonly icons = {
    Activity,
    Eye,
    Music4,
    Plus,
    Shield,
    Trash2,
    UserRoundCheck,
    UserRoundX,
  };

  newUser: { name: string; email: string; password: string; role: 'user' | 'admin' } = {
    name: '',
    email: '',
    password: 'password',
    role: 'user',
  };

  constructor() {
    this.refresh();
  }

  createUser(): void {
    if (!this.newUser.name.trim() || !this.newUser.email.trim()) {
      return;
    }

    this.admin.createUser(this.newUser).subscribe(({ user }) => {
      this.users.update((users) => [user, ...users]);
      this.newUser = { name: '', email: '', password: 'password', role: 'user' };
      this.refreshStats();
    });
  }

  setRole(user: User, role: 'user' | 'admin'): void {
    this.admin.updateUser(user, { role }).subscribe(({ user: updated }) => {
      this.patchUser(updated);
    });
  }

  toggleActive(user: User): void {
    this.admin.updateUser(user, { isActive: !user.isActive }).subscribe(({ user: updated }) => {
      this.patchUser(updated);
    });
  }

  inspectUser(user: User): void {
    this.loadingLibrary.set(true);
    this.selectedUser.set(user);
    this.selectedPlaylistDetail.set(null);

    this.admin.userLibrary(user).subscribe({
      next: (library) => {
        this.selectedLibrary.set(library);
        this.loadingLibrary.set(false);
      },
      error: () => {
        this.selectedLibrary.set(null);
        this.loadingLibrary.set(false);
      },
    });
  }

  viewPlaylist(playlist: Playlist): void {
    const user = this.selectedUser();
    if (!user) return;

    this.admin.userPlaylist(user, playlist.id).subscribe((detail) => {
      this.selectedPlaylistDetail.set(detail);
    });
  }

  removeFavorite(spotifyTrackId: string): void {
    const user = this.selectedUser();
    if (!user) return;

    this.admin.removeFavorite(user, spotifyTrackId).subscribe(() => {
      this.selectedLibrary.update((library) =>
        library
          ? {
              ...library,
              stats: { ...library.stats, favorites: Math.max(0, library.stats.favorites - 1) },
              favoriteTracks: library.favoriteTracks.filter((track) => track.id !== spotifyTrackId),
            }
          : null
      );
      this.reloadUsersAndStats();
    });
  }

  removeFollowedArtist(spotifyArtistId: string): void {
    const user = this.selectedUser();
    if (!user) return;

    this.admin.removeFollowedArtist(user, spotifyArtistId).subscribe(() => {
      this.selectedLibrary.update((library) =>
        library
          ? {
              ...library,
              stats: { ...library.stats, followedArtists: Math.max(0, library.stats.followedArtists - 1) },
              followedArtists: library.followedArtists.filter((artist) => artist.id !== spotifyArtistId),
            }
          : null
      );
      this.reloadUsersAndStats();
    });
  }

  removePlaylist(playlist: Playlist): void {
    const user = this.selectedUser();
    if (!user) return;

    this.admin.deletePlaylist(user, playlist.id).subscribe(() => {
      this.selectedLibrary.update((library) =>
        library
          ? {
              ...library,
              stats: { ...library.stats, playlists: Math.max(0, library.stats.playlists - 1) },
              playlists: library.playlists.filter((item) => item.id !== playlist.id),
            }
          : null
      );

      if (this.selectedPlaylistDetail()?.playlist.id === playlist.id) {
        this.selectedPlaylistDetail.set(null);
      }

      this.reloadUsersAndStats();
    });
  }

  deleteUser(user: User): void {
    this.admin.deleteUser(user).subscribe(() => {
      this.users.update((users) => users.filter((item) => item.id !== user.id));

      if (this.selectedUser()?.id === user.id) {
        this.selectedUser.set(null);
        this.selectedLibrary.set(null);
        this.selectedPlaylistDetail.set(null);
      }

      this.refreshStats();
      this.refreshActivity();
    });
  }

  shortDate(value: string): string {
    return value.slice(0, 16).replace('T', ' ');
  }

  private refresh(): void {
    this.reloadUsersAndStats();
    this.refreshActivity();
  }

  private reloadUsersAndStats(): void {
    this.admin.users().subscribe((users) => this.users.set(users));
    this.refreshStats();
  }

  private refreshStats(): void {
    this.admin.stats().subscribe((stats) => this.stats.set(stats));
  }

  private refreshActivity(): void {
    this.admin.activity().subscribe((activity) => this.activity.set(activity));
  }

  private patchUser(updated: User): void {
    this.users.update((users) => users.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)));

    if (this.selectedUser()?.id === updated.id) {
      this.selectedUser.set({ ...this.selectedUser()!, ...updated });
    }

    if (this.selectedLibrary()?.user.id === updated.id) {
      this.selectedLibrary.update((library) =>
        library ? { ...library, user: { ...library.user, ...updated } } : null
      );
    }
  }
}
