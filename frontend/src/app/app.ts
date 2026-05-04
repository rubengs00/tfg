import { Component, inject } from '@angular/core';
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

@Component({
  selector: 'app-root',
  imports: [FormsModule, LucideAngularModule, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App {
  protected readonly auth = inject(AuthService);
  protected readonly player = inject(PlayerService);
  private readonly router = inject(Router);

  search = '';
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
}
