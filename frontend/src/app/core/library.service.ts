import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { map, switchMap, tap } from 'rxjs';

import { API_BASE_URL } from './api';
import {
  PlaylistDetail,
  Playlist,
  SpotifyArtist,
  SpotifyTrack,
  unwrapList,
} from './models';

@Injectable({ providedIn: 'root' })
export class LibraryService {
  private readonly http = inject(HttpClient);

  private readonly favoriteTrackIdsState = signal<string[]>([]);
  private readonly favoriteTracksState = signal<SpotifyTrack[]>([]);
  private readonly followedArtistIdsState = signal<string[]>([]);
  private readonly followedArtistsState = signal<SpotifyArtist[]>([]);

  readonly favoriteTrackIds = this.favoriteTrackIdsState.asReadonly();
  readonly favoriteTracks = this.favoriteTracksState.asReadonly();
  readonly followedArtistIds = this.followedArtistIdsState.asReadonly();
  readonly followedArtistsStateView = this.followedArtistsState.asReadonly();

  /* =====================================================
     FAVORITES
  ===================================================== */

  favorites() {
    return this.http
      .get<{ tracks: SpotifyTrack[] }>(`${API_BASE_URL}/me/favorites`)
      .pipe(
        map((response) => response.tracks ?? []),
        tap((tracks) => this.setFavoriteTracks(tracks))
      );
  }

  addFavorite(spotifyTrackId: string) {
    // Actualización optimista: agregar a favoritos localmente
    this.favoriteTrackIdsState.update((ids) => {
      if (ids.includes(spotifyTrackId)) return ids;
      return [...ids, spotifyTrackId];
    });

    return this.http
      .post(`${API_BASE_URL}/me/favorites`, {
        spotifyTrackId,
      })
      .pipe(
        switchMap(() => this.favorites()),
        // Si falla, revertir el cambio
        tap({
          error: () => {
            this.favoriteTrackIdsState.update((ids) =>
              ids.filter((id) => id !== spotifyTrackId)
            );
          },
        })
      );
  }

  removeFavorite(spotifyTrackId: string) {
    // Actualización optimista: remover de favoritos localmente
    this.favoriteTrackIdsState.update((ids) =>
      ids.filter((id) => id !== spotifyTrackId)
    );

    return this.http
      .delete(`${API_BASE_URL}/me/favorites`, {
        body: { spotifyTrackId },
      })
      .pipe(
        switchMap(() => this.favorites()),
        // Si falla, revertir el cambio
        tap({
          error: () => {
            this.favoriteTrackIdsState.update((ids) => [...ids, spotifyTrackId]);
          },
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
        map((response) => response.artists ?? []),
        tap((artists) => this.setFollowedArtists(artists))
      );
  }

  followArtist(spotifyArtistId: string) {
    // Actualización optimista: agregar a artistas seguidos localmente
    this.followedArtistIdsState.update((ids) => {
      if (ids.includes(spotifyArtistId)) return ids;
      return [...ids, spotifyArtistId];
    });

    return this.http
      .post(`${API_BASE_URL}/me/followed-artists`, {
        spotifyArtistId,
      })
      .pipe(
        switchMap(() => this.followedArtists()),
        // Si falla, revertir el cambio
        tap({
          error: () => {
            this.followedArtistIdsState.update((ids) =>
              ids.filter((id) => id !== spotifyArtistId)
            );
          },
        })
      );
  }

  unfollowArtist(spotifyArtistId: string) {
    // Actualización optimista: remover de artistas seguidos localmente
    this.followedArtistIdsState.update((ids) =>
      ids.filter((id) => id !== spotifyArtistId)
    );

    return this.http
      .delete(`${API_BASE_URL}/me/followed-artists`, {
        body: { spotifyArtistId },
      })
      .pipe(
        switchMap(() => this.followedArtists()),
        // Si falla, revertir el cambio
        tap({
          error: () => {
            this.followedArtistIdsState.update((ids) => [...ids, spotifyArtistId]);
          },
        })
      );
  }

  /* =====================================================
     PLAYLISTS
  ===================================================== */

  playlists() {
    return this.http
      .get<{ playlists: Playlist[] | { data: Playlist[] } }>(`${API_BASE_URL}/me/playlists`)
      .pipe(map((response) => unwrapList(response.playlists)));
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
    return this.http.get<PlaylistDetail>(`${API_BASE_URL}/me/playlists/${id}`);
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

  clearState(): void {
    this.favoriteTrackIdsState.set([]);
    this.favoriteTracksState.set([]);
    this.followedArtistIdsState.set([]);
    this.followedArtistsState.set([]);
  }

  hydrateFavoriteTracks(tracks: SpotifyTrack[]): void {
    this.setFavoriteTracks(tracks);
  }

  hydrateFollowedArtists(artists: SpotifyArtist[]): void {
    this.setFollowedArtists(artists);
  }

  private setFavoriteTracks(tracks: SpotifyTrack[]): void {
    this.favoriteTracksState.set(tracks);
    this.favoriteTrackIdsState.set(tracks.map((track) => track.id));
  }

  private setFollowedArtists(artists: SpotifyArtist[]): void {
    this.followedArtistsState.set(artists);
    this.followedArtistIdsState.set(artists.map((artist) => artist.id));
  }
}
