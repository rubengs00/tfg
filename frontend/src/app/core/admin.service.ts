import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import { ActivityLog, Artist, Song, unwrapList, User } from './models';

export interface AdminStats {
  totals: {
    users: number;
    artists: number;
    songs: number;
    playlists: number;
    activityEvents: number;
  };
  topArtists: Artist[] | { data: Artist[] };
  topSongs: Song[] | { data: Song[] };
  recentActivity: ActivityLog[] | { data: ActivityLog[] };
}

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);

  stats() {
    return this.http.get<AdminStats>(`${API_BASE_URL}/admin/stats`).pipe(
      map((stats) => ({
        totals: stats.totals,
        topArtists: unwrapList(stats.topArtists),
        topSongs: unwrapList(stats.topSongs),
        recentActivity: unwrapList(stats.recentActivity),
      })),
    );
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
    return this.http.get<{ activity: ActivityLog[] | { data: ActivityLog[] } }>(`${API_BASE_URL}/admin/activity`)
      .pipe(map((response) => unwrapList(response.activity)));
  }
}
