/* =====================================================
   GENERIC API HELPERS
===================================================== */

export interface ApiPage<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    total?: number;
  };
}

export type ApiList<T> = T[] | ApiPage<T>;

export function unwrapList<T>(value: ApiList<T> | undefined | null): T[] {
  if (!value) return [];
  return Array.isArray(value) ? value : value.data;
}

/* =====================================================
   USER DOMAIN (LOCAL DB)
===================================================== */

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'user' | 'admin';
  avatar_url: string | null;
  twoFactorEnabled?: boolean;
  isActive?: boolean;
}

/* =====================================================
   SPOTIFY DOMAIN (REMOTE SOURCE OF TRUTH)
===================================================== */

export interface SpotifyImage {
  url: string;
  width?: number;
  height?: number;
}

export interface SpotifyArtist {
  id: string;
  name: string;
  genres?: string[];
  popularity?: number;
  followers?: {
    total: number;
  };
  images?: SpotifyImage[];
}

export interface SpotifyAlbum {
  id: string;
  name: string;
  release_date?: string;
  total_tracks?: number;
  images?: SpotifyImage[];
  artists?: SpotifyArtist[];
}

export interface SpotifyTrack {
  id: string;
  name: string;
  duration_ms: number;
  preview_url: string | null;
  explicit: boolean;
  popularity?: number;
  track_number?: number;
  album?: SpotifyAlbum;
  artists?: SpotifyArtist[];
}

/* =====================================================
   PLAYLISTS (LOCAL STATE + SPOTIFY TRACKS)
===================================================== */

export interface Playlist {
  id: number;
  name: string;
  description: string | null;
  created_at?: string;

  // Campos opcionales usados por la UI (pueden venir o no del backend)
  coverUrl?: string | null;
  songsCount?: number;
}

export interface LoginStartResponse {
  // Cuando el backend exige 2FA, devuelve estos campos.
  // Si no exige 2FA, normalmente devuelve SessionResponse (token + user).
  requiresTwoFactor?: boolean;
  challengeId?: string;
  debugCode?: string;
}

export interface SessionResponse {
  user: User;
  token?: string;
}

/* =====================================================
   HOME & SEARCH (Spotify-first)
===================================================== */

export interface HomeData {
  source: 'spotify';
  genre?: string;
  artists: SpotifyArtist[];
  albums: SpotifyAlbum[];
  tracks: SpotifyTrack[];
}

export interface SearchResults extends HomeData {}

/* =====================================================
   PROFILE (Hybrid: Local + Spotify)
===================================================== */

export interface ProfileData {
  user: User;
  stats: {
    playlists: number;
    favorites: number;
    followedArtists: number;
  };
  playlists: Playlist[];
  favoriteTracks: SpotifyTrack[];
  followedArtists: SpotifyArtist[];
}

/* =====================================================
   UTILITIES
===================================================== */

export function formatDuration(ms: number): string {
  const seconds = Math.floor(ms / 1000);
  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;
  return `${minutes}:${rest.toString().padStart(2, '0')}`;
}
