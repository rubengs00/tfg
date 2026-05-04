import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Plus, Shield, Trash2 } from 'lucide-angular';

import { AdminService } from '../../core/admin.service';
import { User } from '../../core/models';

@Component({
  selector: 'app-admin-page',
  imports: [FormsModule, LucideAngularModule],
  template: `
    <section class="page-hero page-hero--compact">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Panel de control</h1>
      </div>
      <p>Usuarios, estadisticas y actividad reciente</p>
    </section>

    @if (totals(); as stats) {
      <section class="stats-grid">
        <div><span>Usuarios</span><strong>{{ stats.users }}</strong></div>
        <div><span>Artistas</span><strong>{{ stats.artists }}</strong></div>
        <div><span>Canciones</span><strong>{{ stats.songs }}</strong></div>
        <div><span>Eventos</span><strong>{{ stats.activityEvents }}</strong></div>
      </section>
    }

    <section class="admin-grid">
      <div class="admin-panel">
        <div class="section-heading">
          <h2>Usuarios</h2>
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
            <div class="admin-table__row">
              <div>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }}</span>
              </div>
              <select [ngModel]="user.role" (ngModelChange)="setRole(user, $event)">
                <option value="user">user</option>
                <option value="admin">admin</option>
              </select>
              <button class="icon-button" type="button" (click)="deleteUser(user)" title="Eliminar">
                <lucide-icon [img]="icons.Trash2" [size]="17"></lucide-icon>
              </button>
            </div>
          }
        </div>
      </div>

      <div class="admin-panel">
        <div class="section-heading">
          <h2>Top artistas</h2>
        </div>
        <div class="compact-list">
          @for (artist of topArtists(); track artist.id) {
            <div><strong>{{ artist.name }}</strong><span>{{ artist.followers.toLocaleString('es-ES') }} seguidores</span></div>
          }
        </div>

        <div class="section-heading section-heading--spaced">
          <h2>Top canciones</h2>
        </div>
        <div class="compact-list">
          @for (song of topSongs(); track song.id) {
            <div><strong>{{ song.title }}</strong><span>{{ song.album?.artist?.name }}</span></div>
          }
        </div>
      </div>
    </section>

    <section class="content-section">
      <div class="section-heading">
        <h2>Actividad reciente</h2>
        <lucide-icon [img]="icons.Shield" [size]="18"></lucide-icon>
      </div>
      <div class="activity-list">
        @for (event of activity(); track event.id) {
          <div>
            <strong>{{ event.action }}</strong>
            <span>{{ event.user?.name ?? 'Sistema' }} / {{ event.resourceType }} / {{ shortDate(event.createdAt) }}</span>
          </div>
        }
      </div>
    </section>
  `,
})
export class AdminPageComponent {
  private readonly admin = inject(AdminService);

  readonly users = signal<User[]>([]);
  // Admin desactivado en modo Spotify-first: solo mantenemos usuarios.
  readonly totals = signal<{ users: number; artists: number; songs: number; playlists: number; activityEvents: number } | null>(null);
  readonly topArtists = signal<any[]>([]);
  readonly topSongs = signal<any[]>([]);
  readonly activity = signal<any[]>([]);
  readonly icons = { Plus, Shield, Trash2 };

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
    });
  }

  setRole(user: User, role: 'user' | 'admin'): void {
    this.admin.updateUser(user, { role }).subscribe(({ user: updated }) => {
      this.users.update((users) => users.map((item) => (item.id === updated.id ? updated : item)));
    });
  }

  deleteUser(user: User): void {
    this.admin.deleteUser(user).subscribe(() => {
      this.users.update((users) => users.filter((item) => item.id !== user.id));
    });
  }

  shortDate(value: string): string {
    return value.slice(0, 16).replace('T', ' ');
  }

  private refresh(): void {
    // En Spotify-first no garantizamos stats/actividad/top*.
    // Solo refrescamos usuarios para evitar errores de tipado y compilación.
    this.admin.users().subscribe((users) => this.users.set(users));
  }
}
