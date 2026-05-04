import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import { unwrapList, User } from './models';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);

  /**
   * Admin está fuera del alcance Spotify-first.
   * Dejamos métodos mínimos para no romper compilación.
   */
  stats() {
    return this.http.get(`${API_BASE_URL}/admin/stats`);
  }

  users() {
    return this.http.get<{ users: User[] | { data: User[] } }>(`${API_BASE_URL}/admin/users`)
      .pipe(map((response) => unwrapList(response.users)));
  }

  createUser(payload: { name: string; email: string; password: string; role: 'user' | 'admin' }) {
    return this.http.post<{ user: User }>(`${API_BASE_URL}/admin/users`, payload);
  }

  updateUser(user: User, patch: Partial<{ role: 'user' | 'admin'; isActive: boolean; twoFactorEnabled: boolean }>) {
    return this.http.patch<{ user: User }>(`${API_BASE_URL}/admin/users/${user.id}`, patch);
  }

  deleteUser(user: User) {
    return this.http.delete(`${API_BASE_URL}/admin/users/${user.id}`);
  }

  activity() {
    return this.http.get(`${API_BASE_URL}/admin/activity`);
  }
}
