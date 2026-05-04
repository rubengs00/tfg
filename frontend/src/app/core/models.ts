export interface ApiPage<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    total?: number;
  };
}

export type ApiList<T> = T[] | ApiPage<T>;

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'user' | 'admin';
  avatarUrl: string | null;
  twoFactorEnabled: boolean;
  isActive: boolean;
  createdAt?: string;
}

export interface Artist {
  id: number;
  spotifyId?: string | null;
  name: string;
  slug: string;
  genre: string | null;
  followers: number;
  imageUrl: string | null;
  bio: string | null;
  popularity: number;
  albums?: Album[];
  isFollowed?: boolean;
}

export interface Album {
  id: number;
  spotifyId?: string | null;
  artistId: number;
  title: string;
  slug: string;
  coverUrl: string | null;
  releaseYear: number | null;
  totalTracks: number;
  artist?: Artist;
  songs?: Song[];
}

export interface Song {
  id: number;
  spotifyId?: string | null;
  albumId: number;
  title: string;
  durationSeconds: number;
  previewUrl: string | null;
  trackNumber: number;
  explicit: boolean;
  popularity: number;
  album?: Album;
  isFavorite?: boolean;
}

export interface Playlist {
  id: number;
  name: string;
  description: string | null;
  coverUrl: string | null;
  isPublic: boolean;
  songsCount: number;
  songs?: Song[];
  createdAt?: string;
}

export interface ActivityLog {
  id: number;
  action: string;
  resourceType: string;
  resourceId: number | null;
  metadata: Record<string, unknown> | null;
  user?: User;
  createdAt: string;
}

export interface HomeData {
  artists: Artist[];
  albums: Album[];
  songs: Song[];
}

export interface SearchResults extends HomeData {
  source: 'local' | 'spotify';
}

export interface ProfileData {
  user: User;
  stats: {
    playlists: number;
    favorites: number;
    followedArtists: number;
  };
  followedArtists: Artist[];
  playlists: Playlist[];
  favoriteSongs: Song[];
}

export interface LoginStartResponse {
  message: string;
  requiresTwoFactor: true;
  challengeId: string;
  expiresAt: string;
  debugCode: string | null;
}

export interface SessionResponse {
  token: string;
  tokenType: 'Bearer';
  expiresAt: string;
  user: User;
}

export function unwrapList<T>(value: ApiList<T> | undefined | null): T[] {
  if (!value) {
    return [];
  }

  return Array.isArray(value) ? value : value.data;
}

export function formatDuration(seconds: number): string {
  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;
  return `${minutes}:${rest.toString().padStart(2, '0')}`;
}
