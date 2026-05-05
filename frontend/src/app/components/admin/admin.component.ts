import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  Activity,
  AlertTriangle,
  BarChart3,
  CheckCircle2,
  FileWarning,
  ListMusic,
  LucideAngularModule,
  Music4,
  Plus,
  Search,
  ShieldCheck,
  Trash2,
  UserRoundCheck,
  UserRoundX,
  Users,
  X,
} from 'lucide-angular';

import { AdminService } from '../../services/admin.service';
import { AuthService } from '../../services/auth.service';
import {
  ActivityEvent,
  AdminStatsResponse,
  AdminUserLibrary,
  Playlist,
  PlaylistDetail,
  User,
} from '../../interfaces/music.interfaces';
import { SongRowComponent } from '../song-row/song-row.component';

type AdminTab = 'stats' | 'users' | 'logs';
type LogMode = 'all' | 'errors';
type AdminStatusKind = 'info' | 'success' | 'error';

interface AdminStatus {
  kind: AdminStatusKind;
  message: string;
}

interface AdminConfirmation {
  kind: 'deleteUser' | 'deletePlaylist';
  title: string;
  message: string;
  confirmLabel: string;
  busyLabel: string;
  user?: User;
  playlist?: Playlist;
}

interface UserDraft {
  name: string;
  email: string;
  password: string;
  role: 'user' | 'admin';
  isActive: boolean;
  twoFactorEnabled: boolean;
}

@Component({
  selector: 'app-admin',
  standalone: true,
  imports: [FormsModule, LucideAngularModule, SongRowComponent],
  templateUrl: './admin.component.html',
  styleUrl: './admin.component.scss',
})
export class AdminComponent {
  private readonly admin = inject(AdminService);
  readonly auth = inject(AuthService);

  readonly activeTab = signal<AdminTab>('stats');
  readonly logMode = signal<LogMode>('all');
  readonly stats = signal<AdminStatsResponse | null>(null);
  readonly users = signal<User[]>([]);
  readonly logs = signal<ActivityEvent[]>([]);
  readonly selectedUser = signal<User | null>(null);
  readonly selectedLibrary = signal<AdminUserLibrary | null>(null);
  readonly selectedPlaylistDetail = signal<PlaylistDetail | null>(null);
  readonly loadingLibrary = signal(false);
  readonly savingUser = signal(false);
  readonly confirmingAction = signal(false);
  readonly status = signal<AdminStatus | null>(null);
  readonly confirmation = signal<AdminConfirmation | null>(null);
  readonly feedback = signal('');

  readonly userSearch = signal('');
  private statusTimer: ReturnType<typeof globalThis.setTimeout> | null = null;

  userDraft: UserDraft = {
    name: '',
    email: '',
    password: '',
    role: 'user',
    isActive: true,
    twoFactorEnabled: false,
  };

  newUser: UserDraft = {
    name: '',
    email: '',
    password: 'password',
    role: 'user',
    isActive: true,
    twoFactorEnabled: false,
  };

  readonly icons = {
    Activity,
    AlertTriangle,
    BarChart3,
    CheckCircle2,
    FileWarning,
    ListMusic,
    Music4,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserRoundCheck,
    UserRoundX,
    Users,
    X,
  };

  readonly filteredUsers = computed(() => {
    const query = this.userSearch().trim().toLowerCase();

    if (!query) {
      return this.users();
    }

    return this.users().filter((user) =>
      `${user.name} ${user.email} ${user.role}`.toLowerCase().includes(query)
    );
  });

  readonly maxActivity = computed(() => {
    const totals = this.stats()?.activitySeries.map((point) => point.total) ?? [0];

    return Math.max(1, ...totals);
  });

  constructor() {
    this.refresh();
  }

  headerTitle(): string {
    if (this.activeTab() === 'users') return 'Gestion de usuarios';
    if (this.activeTab() === 'logs') return 'Logs y errores';
    return 'Panel de analiticas';
  }

  headerText(): string {
    if (this.activeTab() === 'users') {
      return 'Edita perfiles, revisa bibliotecas y controla roles o accesos desde un unico sitio.';
    }

    if (this.activeTab() === 'logs') {
      return 'Consulta actividad reciente y fallos reales de API registrados por el backend.';
    }

    return 'Resumen operativo de usuarios, playlists, actividad y salud del sistema.';
  }

  setTab(tab: AdminTab): void {
    this.activeTab.set(tab);

    if (tab === 'logs') {
      this.refreshLogs();
    }
  }

  setLogMode(mode: LogMode): void {
    this.logMode.set(mode);
    this.refreshLogs();
  }

  createUser(): void {
    if (!this.newUser.name.trim() || !this.newUser.email.trim() || !this.newUser.password.trim()) {
      this.feedback.set('Completa nombre, email y password.');
      this.showStatus('error', 'Completa nombre, email y password antes de crear el usuario.');
      return;
    }

    this.feedback.set('');
    this.savingUser.set(true);
    this.showStatus('info', 'Guardando usuario...');

    this.admin.createUser(this.newUser).subscribe({
      next: ({ user }) => {
        this.users.update((users) => [user, ...users]);
        this.newUser = {
          name: '',
          email: '',
          password: 'password',
          role: 'user',
          isActive: true,
          twoFactorEnabled: false,
        };
        this.feedback.set('Usuario creado correctamente.');
        this.showStatus('success', 'Usuario creado correctamente.');
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        const message = this.errorMessage(error, 'No se ha podido crear el usuario.');
        this.feedback.set(message);
        this.showStatus('error', message);
        this.savingUser.set(false);
      },
      complete: () => this.savingUser.set(false),
    });
  }

  openUser(user: User): void {
    this.selectedUser.set(user);
    this.selectedPlaylistDetail.set(null);
    this.userDraft = {
      name: user.name,
      email: user.email,
      password: '',
      role: user.role,
      isActive: user.isActive ?? true,
      twoFactorEnabled: user.twoFactorEnabled ?? false,
    };
    this.loadUserLibrary(user);
  }

  closeUserModal(): void {
    this.selectedUser.set(null);
    this.selectedLibrary.set(null);
    this.selectedPlaylistDetail.set(null);
  }

  saveSelectedUser(): void {
    const user = this.selectedUser();

    if (!user) return;

    const patch: Partial<UserDraft> = {
      name: this.userDraft.name.trim(),
      email: this.userDraft.email.trim(),
      role: this.userDraft.role,
      isActive: this.isCurrentUser(user) ? true : this.userDraft.isActive,
      twoFactorEnabled: this.userDraft.twoFactorEnabled,
    };

    if (this.userDraft.password.trim()) {
      patch.password = this.userDraft.password.trim();
    }

    this.savingUser.set(true);
    this.showStatus('info', 'Guardando cambios del usuario...');

    this.admin.updateUser(user, patch).subscribe({
      next: ({ user: updated }) => {
        this.patchUser(updated);
        this.userDraft.password = '';
        this.feedback.set('Usuario actualizado.');
        this.showStatus('success', 'Usuario actualizado correctamente.');
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        const message = this.errorMessage(error, 'No se ha podido actualizar el usuario.');
        this.feedback.set(message);
        this.showStatus('error', message);
        this.savingUser.set(false);
      },
      complete: () => this.savingUser.set(false),
    });
  }

  toggleActive(user: User): void {
    if (this.isCurrentUser(user)) {
      this.feedback.set('No puedes desactivar tu propia cuenta admin.');
      this.showStatus('error', 'No puedes desactivar tu propia cuenta admin.');
      return;
    }

    const nextActive = !user.isActive;
    this.showStatus('info', nextActive ? 'Activando usuario...' : 'Desactivando usuario...');

    this.admin.updateUser(user, { isActive: nextActive }).subscribe({
      next: ({ user: updated }) => {
        this.patchUser(updated);
        this.showStatus('success', nextActive ? 'Usuario activado.' : 'Usuario desactivado.');
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        this.showStatus('error', this.errorMessage(error, 'No se ha podido actualizar el estado.'));
      },
    });
  }

  deleteUser(user: User): void {
    if (this.isCurrentUser(user)) {
      this.feedback.set('No puedes eliminar tu propia cuenta admin.');
      this.showStatus('error', 'No puedes eliminar tu propia cuenta admin.');
      return;
    }

    this.confirmation.set({
      kind: 'deleteUser',
      title: 'Eliminar usuario',
      message: `Vas a eliminar a ${user.name}. Esta accion tambien eliminara sus datos asociados.`,
      confirmLabel: 'Eliminar usuario',
      busyLabel: 'Eliminando...',
      user,
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

    this.showStatus('info', 'Quitando favorita...');

    this.admin.removeFavorite(user, spotifyTrackId).subscribe({
      next: () => {
        this.selectedLibrary.update((library) =>
          library
            ? {
                ...library,
                stats: { ...library.stats, favorites: Math.max(0, library.stats.favorites - 1) },
                favoriteTracks: library.favoriteTracks.filter((track) => track.id !== spotifyTrackId),
              }
            : null
        );
        this.showStatus('success', 'Favorita eliminada del usuario.');
        this.refreshUsers();
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        this.showStatus('error', this.errorMessage(error, 'No se ha podido quitar la favorita.'));
      },
    });
  }

  removeFollowedArtist(spotifyArtistId: string): void {
    const user = this.selectedUser();

    if (!user) return;

    this.showStatus('info', 'Quitando artista seguido...');

    this.admin.removeFollowedArtist(user, spotifyArtistId).subscribe({
      next: () => {
        this.selectedLibrary.update((library) =>
          library
            ? {
                ...library,
                stats: {
                  ...library.stats,
                  followedArtists: Math.max(0, library.stats.followedArtists - 1),
                },
                followedArtists: library.followedArtists.filter((artist) => artist.id !== spotifyArtistId),
              }
            : null
        );
        this.showStatus('success', 'Artista eliminado de seguidos.');
        this.refreshUsers();
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        this.showStatus('error', this.errorMessage(error, 'No se ha podido quitar el artista.'));
      },
    });
  }

  removePlaylist(playlist: Playlist): void {
    const user = this.selectedUser();

    if (!user) return;

    this.confirmation.set({
      kind: 'deletePlaylist',
      title: 'Eliminar playlist',
      message: `Vas a eliminar "${playlist.name}" de ${user.name}. Las canciones no se borraran del catalogo.`,
      confirmLabel: 'Eliminar playlist',
      busyLabel: 'Eliminando...',
      user,
      playlist,
    });
  }

  cancelConfirmation(): void {
    if (this.confirmingAction()) return;

    this.confirmation.set(null);
  }

  runConfirmedAction(): void {
    const confirmation = this.confirmation();

    if (!confirmation || this.confirmingAction()) return;

    if (confirmation.kind === 'deleteUser' && confirmation.user) {
      this.performDeleteUser(confirmation.user);
      return;
    }

    if (confirmation.kind === 'deletePlaylist' && confirmation.user && confirmation.playlist) {
      this.performDeletePlaylist(confirmation.user, confirmation.playlist);
    }
  }

  isCurrentUser(user: User): boolean {
    return this.auth.user()?.id === user.id;
  }

  avatarInitial(user: User): string {
    return (user.name.charAt(0) || 'U').toUpperCase();
  }

  barHeight(total: number): number {
    return Math.max(8, Math.round((total / this.maxActivity()) * 100));
  }

  eventTitle(event: ActivityEvent): string {
    if (event.action === 'api.request_failed') {
      return `${this.metadata(event, 'status') || 'Error'} ${this.metadata(event, 'method') || ''} ${this.metadata(event, 'path') || ''}`.trim();
    }

    return event.action.replaceAll('.', ' ');
  }

  eventDescription(event: ActivityEvent): string {
    if (event.action === 'api.request_failed') {
      return this.metadata(event, 'message') || 'Peticion fallida';
    }

    return `${event.user?.name ?? 'Sistema'} - ${event.resourceType}`;
  }

  shortDate(value: string | undefined): string {
    if (!value) return 'Sin fecha';

    return new Intl.DateTimeFormat('es-ES', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(value));
  }

  private refresh(): void {
    this.refreshStats();
    this.refreshUsers();
    this.refreshLogs();
  }

  private refreshStats(): void {
    this.admin.stats().subscribe((stats) => this.stats.set(stats));
  }

  private refreshUsers(): void {
    this.admin.users().subscribe((users) => this.users.set(users));
  }

  private refreshLogs(): void {
    const filters = this.logMode() === 'errors' ? { type: 'errors' as const } : {};
    this.admin.activity(filters).subscribe((events) => this.logs.set(events));
  }

  private loadUserLibrary(user: User): void {
    this.loadingLibrary.set(true);
    this.selectedLibrary.set(null);

    this.admin.userLibrary(user).subscribe({
      next: (library) => {
        this.selectedLibrary.set(library);
        this.patchUser(library.user);
        this.loadingLibrary.set(false);
      },
      error: () => {
        this.selectedLibrary.set(null);
        this.loadingLibrary.set(false);
      },
    });
  }

  private patchUser(updated: User): void {
    this.users.update((users) => users.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)));

    if (this.selectedUser()?.id === updated.id) {
      this.selectedUser.set({ ...this.selectedUser()!, ...updated });
      this.userDraft = {
        ...this.userDraft,
        name: updated.name,
        email: updated.email,
        role: updated.role,
        isActive: updated.isActive ?? true,
        twoFactorEnabled: updated.twoFactorEnabled ?? false,
      };
    }

    if (this.selectedLibrary()?.user.id === updated.id) {
      this.selectedLibrary.update((library) =>
        library ? { ...library, user: { ...library.user, ...updated } } : null
      );
    }
  }

  private performDeleteUser(user: User): void {
    this.confirmingAction.set(true);
    this.showStatus('info', 'Eliminando usuario...');

    this.admin.deleteUser(user).subscribe({
      next: () => {
        this.users.update((users) => users.filter((item) => item.id !== user.id));

        if (this.selectedUser()?.id === user.id) {
          this.closeUserModal();
        }

        this.confirmation.set(null);
        this.showStatus('success', 'Usuario eliminado correctamente.');
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        this.showStatus('error', this.errorMessage(error, 'No se ha podido eliminar el usuario.'));
      },
      complete: () => this.confirmingAction.set(false),
    });
  }

  private performDeletePlaylist(user: User, playlist: Playlist): void {
    this.confirmingAction.set(true);
    this.showStatus('info', 'Eliminando playlist...');

    this.admin.deletePlaylist(user, playlist.id).subscribe({
      next: () => {
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

        this.confirmation.set(null);
        this.showStatus('success', 'Playlist eliminada correctamente.');
        this.refreshUsers();
        this.refreshStats();
        this.refreshLogs();
      },
      error: (error) => {
        this.showStatus('error', this.errorMessage(error, 'No se ha podido eliminar la playlist.'));
      },
      complete: () => this.confirmingAction.set(false),
    });
  }

  private showStatus(kind: AdminStatusKind, message: string): void {
    this.status.set({ kind, message });

    if (this.statusTimer) {
      globalThis.clearTimeout(this.statusTimer);
      this.statusTimer = null;
    }

    if (kind !== 'info') {
      this.statusTimer = globalThis.setTimeout(() => {
        if (this.status()?.message === message) {
          this.status.set(null);
        }
      }, 4200);
    }
  }

  private metadata(event: ActivityEvent, key: string): string {
    const value = event.metadata?.[key];

    if (typeof value === 'string' || typeof value === 'number') {
      return String(value);
    }

    return '';
  }

  private errorMessage(error: any, fallback: string): string {
    const errors = error?.error?.errors;
    const firstError = errors ? Object.values(errors)[0] : null;

    if (Array.isArray(firstError) && firstError[0]) {
      return firstError[0];
    }

    return error?.error?.message ?? fallback;
  }
}
