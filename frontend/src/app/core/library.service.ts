import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import {
  Playlist,
  SpotifyArtist,
  SpotifyTrack,
} from './models';

@Injectable({ providedIn: 'root' })
export class LibraryService {
  private readonly http = inject(HttpClient);

  /* =====================================================
     GLOBAL STATE (Spotify IDs)
  ===================================================== */

  readonly favoriteTrackIds = signal<string[]>([]);
  readonly followedArtistIds = signal<string[]>([]);

  /* =====================================================
     FAVORITES
  ===================================================== */

  favorites() {
    return this.http
      .get<{ tracks: SpotifyTrack[] }>(`${API_BASE_URL}/me/favorites`)
      .pipe(
        map((response) => {
          const tracks = response.tracks ?? [];
          this.favoriteTrackIds.set(tracks.map((t) => t.id));
          return tracks;
        })
      );
  }

  addFavorite(spotifyTrackId: string) {
    return this.http
      .post(`${API_BASE_URL}/me/favorites`, {
        spotifyTrackId,
      })
      .pipe(
        map(() => {
          this.favoriteTrackIds.update((ids) =>
            ids.includes(spotifyTrackId)
              ? ids
              : [...ids, spotifyTrackId]
          );
        })
      );
  }

  removeFavorite(spotifyTrackId: string) {
    return this.http
      .delete(`${API_BASE_URL}/me/favorites`, {
        body: { spotifyTrackId },
      })
      .pipe(
        map(() => {
          this.favoriteTrackIds.update((ids) =>
            ids.filter((id) => id !== spotifyTrackId)
          );
        })
      );
  }

  /* =====================================================
     FOLLOWED ARTISTS
  ===================================================== */

  followedArtists() {
    return this.http
      .get<{ artists: SpotifyArtist[] }>(
        `${API_BASE_URL}/me/followed-artists`
      )
      .pipe(
        map((response) => {
          const artists = response.artists ?? [];
          this.followedArtistIds.set(artists.map((a) => a.id));
          return artists;
        })
      );
  }

  followArtist(spotifyArtistId: string) {
    return this.http
      .post(`${API_BASE_URL}/me/followed-artists`, {
        spotifyArtistId,
      })
      .pipe(
        map(() => {
          this.followedArtistIds.update((ids) =>
            ids.includes(spotifyArtistId)
              ? ids
              : [...ids, spotifyArtistId]
          );
        })
      );
  }

  unfollowArtist(spotifyArtistId: string) {
    return this.http
      .delete(`${API_BASE_URL}/me/followed-artists`, {
        body: { spotifyArtistId },
      })
      .pipe(
        map(() => {
          this.followedArtistIds.update((ids) =>
            ids.filter((id) => id !== spotifyArtistId)
          );
        })
      );
  }

  /* =====================================================
     PLAYLISTS
  ===================================================== */

  playlists() {
    return this.http
      .get<{ playlists: Playlist[] }>(`${API_BASE_URL}/me/playlists`)
      .pipe(map((response) => response.playlists));
  }

  createPlaylist(name: string, description = '') {
    return this.http.post<{ playlist: Playlist }>(
      `${API_BASE_URL}/me/playlists`,
      {
        name,
        description,
      }
    );
  }

  playlist(id: number) {
    return this.http.get<{
      playlist: Playlist;
      tracks: SpotifyTrack[];
    }>(`${API_BASE_URL}/me/playlists/${id}`);
  }

  addTrackToPlaylist(playlistId: number, spotifyTrackId: string) {
    return this.http.post(
      `${API_BASE_URL}/me/playlists/${playlistId}/tracks`,
      {
        spotifyTrackId,
      }
    );
  }

  removeTrackFromPlaylist(playlistId: number, spotifyTrackId: string) {
    return this.http.delete(
      `${API_BASE_URL}/me/playlists/${playlistId}/tracks`,
      {
        body: { spotifyTrackId },
      }
    );
  }

  deletePlaylist(playlistId: number) {
    return this.http.delete(
      `${API_BASE_URL}/me/playlists/${playlistId}`
    );
  }

  updatePlaylist(playlistId: number, formData: FormData) {
    formData.append('_method', 'PATCH');

    return this.http
      .post<{ playlist: Playlist }>(`${API_BASE_URL}/me/playlists/${playlistId}`, formData)
      .pipe(map((response) => response.playlist));
  }
}
