import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import { Artist, Playlist, Song, unwrapList } from './models';

@Injectable({ providedIn: 'root' })
export class LibraryService {
  private readonly http = inject(HttpClient);

  favorites() {
    return this.http.get<{ songs: Song[] | { data: Song[] } }>(`${API_BASE_URL}/me/favorites`)
      .pipe(map((response) => unwrapList(response.songs)));
  }

  addFavorite(songId: number) {
    return this.http.put(`${API_BASE_URL}/me/favorites/${songId}`, {});
  }

  removeFavorite(songId: number) {
    return this.http.delete(`${API_BASE_URL}/me/favorites/${songId}`);
  }

  followedArtists() {
    return this.http.get<{ artists: Artist[] | { data: Artist[] } }>(`${API_BASE_URL}/me/followed-artists`)
      .pipe(map((response) => unwrapList(response.artists)));
  }

  followArtist(artistId: number) {
    return this.http.put(`${API_BASE_URL}/me/followed-artists/${artistId}`, {});
  }

  unfollowArtist(artistId: number) {
    return this.http.delete(`${API_BASE_URL}/me/followed-artists/${artistId}`);
  }

  playlists() {
    return this.http.get<{ playlists: Playlist[] | { data: Playlist[] } }>(`${API_BASE_URL}/me/playlists`)
      .pipe(map((response) => unwrapList(response.playlists)));
  }

  createPlaylist(name: string, description = '') {
    return this.http.post<{ playlist: Playlist }>(`${API_BASE_URL}/me/playlists`, {
      name,
      description,
    });
  }

  playlist(id: number) {
    return this.http.get<{ playlist: Playlist }>(`${API_BASE_URL}/me/playlists/${id}`)
      .pipe(map((response) => response.playlist));
  }

  removeSong(playlistId: number, songId: number) {
    return this.http.delete<{ playlist: Playlist }>(`${API_BASE_URL}/me/playlists/${playlistId}/songs/${songId}`)
      .pipe(map((response) => response.playlist));
  }
}
