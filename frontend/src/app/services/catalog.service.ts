import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from '../config/api.config';
import {
  HomeData,
  SearchResults,
  SpotifyAlbum,
  SpotifyArtist,
  SpotifyTrack,
} from '../interfaces/music.interfaces';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly http = inject(HttpClient);



  home() {
    return this.http
      .get<HomeData>(`${API_BASE_URL}/home`);
  }



  search(query: string) {
    return this.http
      .get<SearchResults>(`${API_BASE_URL}/search`, {
        params: { q: query },
      });
  }



  artist(id: string) {
    return this.http.get<{
      artist: SpotifyArtist | null;
      albums: SpotifyAlbum[];
      albumsRetryAfter?: number | null;
    }>(`${API_BASE_URL}/artists/${id}`);
  }



  album(id: string) {
    return this.http.get<{
      album: SpotifyAlbum | null;
      tracks: SpotifyTrack[];
    }>(`${API_BASE_URL}/albums/${id}`);
  }
}
