

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

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'user' | 'admin';
  avatarUrl: string | null;
  twoFactorEnabled?: boolean;
  isActive?: boolean;
  playlistsCount?: number | null;
  favoriteSongsCount?: number | null;
  followedArtistsCount?: number | null;
  createdAt?: string;
}

export interface LocalArtist {
  id: number;
  spotifyId?: string | null;
  name: string;
  slug?: string;
  genre?: string | null;
  followers: number;
  imageUrl?: string | null;
  bio?: string | null;
  popularity?: number;
}

export interface LocalAlbum {
  id: number;
  spotifyId?: string | null;
  artistId?: number;
  title: string;
  slug?: string;
  coverUrl?: string | null;
  releaseYear?: number | null;
  totalTracks?: number;
  artist?: LocalArtist;
}

export interface LocalSong {
  id: number;
  spotifyId?: string | null;
  albumId?: number;
  title: string;
  durationSeconds: number;
  previewUrl?: string | null;
  trackNumber?: number;
  explicit: boolean;
  popularity?: number;
  album?: LocalAlbum;
  isFavorite?: boolean;
}

export interface Playlist {
  id: number;
  name: string;
  description: string | null;
  coverUrl?: string | null;
  isPublic?: boolean;
  songsCount?: number;
  user?: User;
  createdAt?: string;
}

export interface ActivityEvent {
  id: number;
  action: string;
  resourceType: string;
  resourceId: number | null;
  metadata?: Record<string, unknown> | null;
  user?: User | null;
  createdAt: string;
}

export interface AdminTotals {
  users: number;
  artists: number;
  songs: number;
  playlists: number;
  activityEvents: number;
}

export interface AdminHealth {
  admins: number;
  standardUsers: number;
  activeUsers: number;
  inactiveUsers: number;
  twoFactorUsers: number;
  newUsers7d: number;
  errors24h: number;
}

export interface AdminActivityPoint {
  date: string;
  label: string;
  total: number;
}

export interface AdminStatsResponse {
  totals: AdminTotals;
  health: AdminHealth;
  activitySeries: AdminActivityPoint[];
  topArtists: LocalArtist[];
  topSongs: LocalSong[];
  topPlaylists: Playlist[];
  recentActivity: ActivityEvent[];
  recentErrors: ActivityEvent[];
}

export interface AdminUserLibrary {
  user: User;
  stats: {
    playlists: number;
    favorites: number;
    followedArtists: number;
  };
  favoriteTracks: SpotifyTrack[];
  followedArtists: SpotifyArtist[];
  playlists: Playlist[];
}

export interface PlaylistDetail {
  playlist: Playlist;
  tracks: SpotifyTrack[];
}

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

export interface SessionResponse {
  user: User;
  token: string;
  tokenType?: string;
  expiresAt?: string;
}

export interface TwoFactorResponse {
  requiresTwoFactor: true;
  challengeId: string;
  expiresAt: string;
  resendAvailableAt?: string;
  attemptsRemaining?: number;
  user?: User;
  debugCode?: string | null;
}

export type AuthResponse = SessionResponse | TwoFactorResponse;

export interface HomeData {
  source: 'spotify';
  genre?: string;
  artists: SpotifyArtist[];
  albums: SpotifyAlbum[];
  tracks: SpotifyTrack[];
}

export type SearchResults = HomeData;

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

export function formatDuration(ms: number): string {
  const seconds = Math.floor(ms / 1000);
  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;
  return `${minutes}:${rest.toString().padStart(2, '0')}`;
}
