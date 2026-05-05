import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { map, switchMap, tap } from 'rxjs';

import { API_BASE_URL } from '../config/api.config';
import {
  Playlist,
  PlaylistDetail,
  SpotifyArtist,
  SpotifyTrack,
  unwrapList,
} from '../interfaces/music.interfaces';

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

  favorites() {
    return this.http.get<{ tracks: SpotifyTrack[] }>(`${API_BASE_URL}/me/favorites`).pipe(
      map((response) => response.tracks ?? []),
      tap((tracks) => this.setFavoriteTracks(tracks)),
    );
  }

  addFavorite(spotifyTrackId: string, track?: SpotifyTrack) {
    const previousIds = this.favoriteTrackIdsState();
    const previousTracks = this.favoriteTracksState();

    this.favoriteTrackIdsState.update((ids) => [
      spotifyTrackId,
      ...ids.filter((id) => id !== spotifyTrackId),
    ]);

    if (track) {
      this.favoriteTracksState.update((tracks) => [
        track,
        ...tracks.filter((item) => item.id !== spotifyTrackId),
      ]);
    }

    return this.http
      .post(`${API_BASE_URL}/me/favorites`, {
        spotifyTrackId,
      })
      .pipe(
        switchMap(() => this.favorites()),
        tap({
          error: () => {
            this.favoriteTrackIdsState.set(previousIds);
            this.favoriteTracksState.set(previousTracks);
          },
        }),
      );
  }

  removeFavorite(spotifyTrackId: string) {
    const previousIds = this.favoriteTrackIdsState();
    const previousTracks = this.favoriteTracksState();

    this.favoriteTrackIdsState.update((ids) => ids.filter((id) => id !== spotifyTrackId));
    this.favoriteTracksState.update((tracks) => tracks.filter((track) => track.id !== spotifyTrackId));

    return this.http
      .delete(`${API_BASE_URL}/me/favorites`, {
        body: { spotifyTrackId },
      })
      .pipe(
        switchMap(() => this.favorites()),
        tap({
          error: () => {
            this.favoriteTrackIdsState.set(previousIds);
            this.favoriteTracksState.set(previousTracks);
          },
        }),
      );
  }

  followedArtists() {
    return this.http.get<{ artists: SpotifyArtist[] }>(`${API_BASE_URL}/me/followed-artists`).pipe(
      map((response) => response.artists ?? []),
      tap((artists) => this.setFollowedArtists(artists)),
    );
  }

  followArtist(spotifyArtistId: string) {
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
        tap({
          error: () => {
            this.followedArtistIdsState.update((ids) =>
              ids.filter((id) => id !== spotifyArtistId),
            );
          },
        }),
      );
  }

  unfollowArtist(spotifyArtistId: string) {
    this.followedArtistIdsState.update((ids) => ids.filter((id) => id !== spotifyArtistId));

    return this.http
      .delete(`${API_BASE_URL}/me/followed-artists`, {
        body: { spotifyArtistId },
      })
      .pipe(
        switchMap(() => this.followedArtists()),
        tap({
          error: () => {
            this.followedArtistIdsState.update((ids) => [...ids, spotifyArtistId]);
          },
        }),
      );
  }

  playlists() {
    return this.http
      .get<{ playlists: Playlist[] | { data: Playlist[] } }>(`${API_BASE_URL}/me/playlists`)
      .pipe(map((response) => unwrapList(response.playlists)));
  }

  createPlaylist(name: string, description = '') {
    return this.http.post<{ playlist: Playlist }>(`${API_BASE_URL}/me/playlists`, {
      name,
      description,
    });
  }

  playlist(id: number) {
    return this.http.get<PlaylistDetail>(`${API_BASE_URL}/me/playlists/${id}`);
  }

  addTrackToPlaylist(playlistId: number, spotifyTrackId: string) {
    return this.http.post<{ message: string; playlist: Playlist }>(
      `${API_BASE_URL}/me/playlists/${playlistId}/tracks`,
      {
        spotifyTrackId,
      },
    );
  }

  removeTrackFromPlaylist(playlistId: number, spotifyTrackId: string) {
    return this.http.delete<{ message: string; playlist: Playlist }>(
      `${API_BASE_URL}/me/playlists/${playlistId}/tracks`,
      {
        body: { spotifyTrackId },
      },
    );
  }

  deletePlaylist(playlistId: number) {
    return this.http.delete(`${API_BASE_URL}/me/playlists/${playlistId}`);
  }

  updatePlaylist(playlistId: number, formData: FormData) {
    if (!formData.has('_method')) {
      formData.append('_method', 'PATCH');
    }

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
