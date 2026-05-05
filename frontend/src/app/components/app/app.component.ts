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
  Search,
  UserRound,
} from 'lucide-angular';

import { AuthService } from '../../services/auth.service';
import { LibraryService } from '../../services/library.service';
import { AudioPlayerComponent } from '../audio-player/audio-player.component';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    AudioPlayerComponent,
    FormsModule,
    LucideAngularModule,
    RouterLink,
    RouterLinkActive,
    RouterOutlet,
  ],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss',
})
export class AppComponent {
  protected readonly auth = inject(AuthService);
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
    Search,
    UserRound,
  };

  submitSearch(): void {
    const query = this.search.trim();
    void this.router.navigate(['/'], { queryParams: query ? { q: query } : {} });
  }

  logout(): void {
    this.auth.logout().subscribe(() => void this.router.navigate(['/']));
  }
}
