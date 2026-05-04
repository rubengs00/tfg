import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { map } from 'rxjs';

import { API_BASE_URL } from './api';
import { Album, ApiList, Artist, HomeData, SearchResults, Song, unwrapList } from './models';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly http = inject(HttpClient);

  home() {
    return this.http.get<{ artists: ApiList<Artist>; albums: ApiList<Album>; songs: ApiList<Song> }>(`${API_BASE_URL}/home`)
      .pipe(map((data) => this.normalizeHome(data)));
  }

  search(query: string) {
    return this.http.get<{ source: 'local' | 'spotify'; artists: ApiList<Artist>; albums: ApiList<Album>; songs: ApiList<Song> }>(`${API_BASE_URL}/search`, {
      params: { q: query },
    }).pipe(map((data) => ({ source: data.source, ...this.normalizeHome(data) }) satisfies SearchResults));
  }

  artist(id: number) {
    return this.http.get<Artist>(`${API_BASE_URL}/artists/${id}`);
  }

  album(id: number) {
    return this.http.get<Album>(`${API_BASE_URL}/albums/${id}`);
  }

  songs() {
    return this.http.get<ApiList<Song>>(`${API_BASE_URL}/songs`).pipe(map(unwrapList));
  }

  private normalizeHome(data: { artists: ApiList<Artist>; albums: ApiList<Album>; songs: ApiList<Song> }): HomeData {
    return {
      artists: unwrapList(data.artists),
      albums: unwrapList(data.albums),
      songs: unwrapList(data.songs),
    };
  }
}
