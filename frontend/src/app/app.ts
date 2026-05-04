import { Component, effect, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import {
  Heart,
  Home,
  LayoutDashboard,
  ListMusic,
  LogIn,
  LogOut,
  LucideAngularModule,
  Music2,
  Pause,
  Play,
  Search,
  UserRound,
  Volume2,
} from 'lucide-angular';

import { AuthService } from './core/auth.service';
import { PlayerService } from './core/player.service';
import { LibraryService } from './core/library.service';

@Component({
  selector: 'app-root',
  imports: [FormsModule, LucideAngularModule, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App {
  protected readonly auth = inject(AuthService);
  protected readonly player = inject(PlayerService);
  private readonly library = inject(LibraryService);
  private readonly router = inject(Router);

  search = '';

  constructor() {
    effect(() => {
      if (this.auth.isLoggedIn()) {
        this.library.favorites().subscribe();
        this.library.followedArtists().subscribe();
        return;
      }

      this.library.clearState();
    });
  }
  readonly icons = {
    Heart,
    Home,
    LayoutDashboard,
    ListMusic,
    LogIn,
    LogOut,
    Music2,
    Pause,
    Play,
    Search,
    UserRound,
    Volume2,
  };

  submitSearch(): void {
    const query = this.search.trim();
    void this.router.navigate(['/'], { queryParams: query ? { q: query } : {} });
  }

  logout(): void {
    this.auth.logout().subscribe(() => void this.router.navigate(['/']));
  }

  setVolume(value: string | number): void {
    this.player.setVolume(Number(value));
  }

  formatTime(seconds: number): string {
    if (!seconds || isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }
}
