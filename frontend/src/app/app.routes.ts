import { Routes } from '@angular/router';

import { AdminComponent } from './components/admin/admin.component';
import { AlbumComponent } from './components/album/album.component';
import { ArtistComponent } from './components/artist/artist.component';
import { FavoritesComponent } from './components/favorites/favorites.component';
import { HomeComponent } from './components/home/home.component';
import { LoginComponent } from './components/login/login.component';
import { PlaylistsComponent } from './components/playlists/playlists.component';
import { ProfileComponent } from './components/profile/profile.component';
import { RegisterComponent } from './components/register/register.component';
import { adminGuard, authGuard } from './guards/auth.guard';

export const routes: Routes = [
  { path: '', component: HomeComponent, title: 'MusicHub' },
  { path: 'artists/:id', component: ArtistComponent, title: 'Artista | MusicHub' },
  { path: 'albums/:id', component: AlbumComponent, title: 'Album | MusicHub' },
  { path: 'favorites', component: FavoritesComponent, canActivate: [authGuard], title: 'Favoritos | MusicHub' },
  { path: 'playlists', component: PlaylistsComponent, canActivate: [authGuard], title: 'Playlists | MusicHub' },
  { path: 'profile', component: ProfileComponent, canActivate: [authGuard], title: 'Perfil | MusicHub' },
  { path: 'admin', component: AdminComponent, canActivate: [authGuard, adminGuard], title: 'Admin | MusicHub' },
  { path: 'login', component: LoginComponent, title: 'Login | MusicHub' },
  { path: 'register', component: RegisterComponent, title: 'Registro | MusicHub' },
  { path: '**', redirectTo: '' },
];
