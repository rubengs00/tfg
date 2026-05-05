import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import {
  AdminStatsResponse,
  AdminUserLibrary,
  ActivityEvent,
  PlaylistDetail,
  User,
  unwrapList,
} from './models';

@Injectable({ providedIn: 'root' })
export class AdminService {
  private readonly http = inject(HttpClient);

  stats() {
    return this.http.get<AdminStatsResponse>(`${API_BASE_URL}/admin/stats`);
  }

  users() {
    return this.http
      .get<{ users: User[] | { data: User[] } }>(`${API_BASE_URL}/admin/users`)
      .pipe(map((response) => unwrapList(response.users)));
  }

  createUser(payload: {
    name: string;
    email: string;
    password: string;
    role: 'user' | 'admin';
    isActive?: boolean;
    twoFactorEnabled?: boolean;
  }) {
    return this.http.post<{ user: User }>(`${API_BASE_URL}/admin/users`, payload);
  }

  updateUser(
    user: User,
    patch: Partial<{
      name: string;
      email: string;
      password: string;
      role: 'user' | 'admin';
      isActive: boolean;
      twoFactorEnabled: boolean;
    }>
  ) {
    return this.http.patch<{ user: User }>(`${API_BASE_URL}/admin/users/${user.id}`, patch);
  }

  deleteUser(user: User) {
    return this.http.delete(`${API_BASE_URL}/admin/users/${user.id}`);
  }

  activity(filters: { type?: 'errors'; action?: string; userId?: number } = {}) {
    return this.http
      .get<{ activity: ActivityEvent[] | { data: ActivityEvent[] } }>(`${API_BASE_URL}/admin/activity`, {
        params: Object.fromEntries(
          Object.entries(filters)
            .filter(([, value]) => value !== undefined && value !== '')
            .map(([key, value]) => [key, String(value)])
        ),
      })
      .pipe(map((response) => unwrapList(response.activity)));
  }

  userLibrary(user: User) {
    return this.http.get<AdminUserLibrary>(`${API_BASE_URL}/admin/users/${user.id}/library`);
  }

  userPlaylist(user: User, playlistId: number) {
    return this.http.get<PlaylistDetail>(`${API_BASE_URL}/admin/users/${user.id}/playlists/${playlistId}`);
  }

  removeFavorite(user: User, spotifyTrackId: string) {
    return this.http.delete(`${API_BASE_URL}/admin/users/${user.id}/favorites/${spotifyTrackId}`);
  }

  removeFollowedArtist(user: User, spotifyArtistId: string) {
    return this.http.delete(`${API_BASE_URL}/admin/users/${user.id}/followed-artists/${spotifyArtistId}`);
  }

  deletePlaylist(user: User, playlistId: number) {
    return this.http.delete(`${API_BASE_URL}/admin/users/${user.id}/playlists/${playlistId}`);
  }
}
