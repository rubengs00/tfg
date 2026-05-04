import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import {
  HomeData,
  SearchResults,
  SpotifyAlbum,
  SpotifyArtist,
  SpotifyTrack,
} from './models';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly http = inject(HttpClient);

  /* =====================================================
     HOME (Spotify-first)
  ===================================================== */

  home() {
    return this.http
      .get<HomeData>(`${API_BASE_URL}/home`);
  }

  /* =====================================================
     SEARCH
  ===================================================== */

  search(query: string) {
    return this.http
      .get<SearchResults>(`${API_BASE_URL}/search`, {
        params: { q: query },
      });
  }

  /* =====================================================
     ARTIST
  ===================================================== */

  artist(id: string) {
    return this.http.get<{
      artist: SpotifyArtist | null;
      albums: SpotifyAlbum[];
    }>(`${API_BASE_URL}/artists/${id}`);
  }

  /* =====================================================
     ALBUM
  ===================================================== */

  album(id: string) {
    return this.http.get<{
      album: SpotifyAlbum | null;
      tracks: SpotifyTrack[];
    }>(`${API_BASE_URL}/albums/${id}`);
  }
}
