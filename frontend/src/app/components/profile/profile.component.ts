import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';

import { AuthService } from '../../core/auth.service';
import { LibraryService } from '../../core/library.service';
import { ProfileData } from '../../core/models';
import { ProfileService } from '../../core/profile.service';
import { MediaCardComponent } from '../media-card/media-card.component';
import { SongRowComponent } from '../song-row/song-row.component';
import { ProfileEditModalComponent } from '../profile-edit-modal/profile-edit-modal.component';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, MediaCardComponent, SongRowComponent, ProfileEditModalComponent],
  templateUrl: './profile.component.html',
  styleUrl: './profile.component.scss',
})
export class ProfileComponent {
  readonly auth = inject(AuthService);
  private readonly profile = inject(ProfileService);
  private readonly library = inject(LibraryService);

  readonly data = signal<ProfileData | null>(null);
  readonly loading = signal(true);
  readonly editing = signal(false);
  readonly favoriteTracks = computed(() => this.library.favoriteTracks());
  readonly followedArtists = computed(() => this.library.followedArtistsStateView());

  constructor() {
    this.profile.profile().subscribe({
      next: (profile) => {
        this.data.set(profile);
        this.auth.updateUser(profile.user);
        this.library.hydrateFavoriteTracks(profile.favoriteTracks ?? []);
        this.library.hydrateFollowedArtists(profile.followedArtists ?? []);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });

    this.library.favorites().subscribe();
    this.library.followedArtists().subscribe();
  }
}



