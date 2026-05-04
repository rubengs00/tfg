import { Routes } from '@angular/router';

import { adminGuard, authGuard } from './core/auth.guard';
import { AdminPageComponent } from './features/admin/admin-page.component';
import { AlbumPageComponent } from './features/catalog/album-page.component';
import { ArtistPageComponent } from './features/catalog/artist-page.component';
import { HomePageComponent } from './features/home/home-page.component';
import { FavoritesPageComponent } from './features/library/favorites-page.component';
import { PlaylistsPageComponent } from './features/library/playlists-page.component';
import { LoginPageComponent } from './features/login/login-page.component';
import { ProfilePageComponent } from './features/profile/profile-page.component';

export const routes: Routes = [
  { path: '', component: HomePageComponent, title: 'MusicHub' },
  { path: 'artists/:id', component: ArtistPageComponent, title: 'Artista | MusicHub' },
  { path: 'albums/:id', component: AlbumPageComponent, title: 'Album | MusicHub' },
  { path: 'favorites', component: FavoritesPageComponent, canActivate: [authGuard], title: 'Favoritos | MusicHub' },
  { path: 'playlists', component: PlaylistsPageComponent, canActivate: [authGuard], title: 'Playlists | MusicHub' },
  { path: 'profile', component: ProfilePageComponent, canActivate: [authGuard], title: 'Perfil | MusicHub' },
  { path: 'admin', component: AdminPageComponent, canActivate: [authGuard, adminGuard], title: 'Admin | MusicHub' },
  { path: 'login', component: LoginPageComponent, title: 'Login | MusicHub' },
  { path: '**', redirectTo: '' },
];
