import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Music2, Pause, Play, Volume2, VolumeX, X } from 'lucide-angular';

import type { SpotifyTrack } from '../../interfaces/music.interfaces';
import { PlayerService } from '../../services/player.service';

@Component({
  selector: 'app-audio-player',
  standalone: true,
  imports: [FormsModule, LucideAngularModule],
  templateUrl: './audio-player.component.html',
  styleUrl: './audio-player.component.scss',
})
export class AudioPlayerComponent {
  protected readonly player = inject(PlayerService);

  readonly icons = {
    Music2,
    Pause,
    Play,
    Volume2,
    VolumeX,
    X,
  };

  seek(value: string | number): void {
    this.player.seek(value);
  }

  setVolume(value: string | number): void {
    this.player.setVolume(Number(value));
  }

  trackArtist(track: SpotifyTrack): string {
    const artists = track.artists?.map((artist) => artist.name).filter(Boolean);
    if (artists?.length) return artists.join(', ');

    const albumArtists = track.album?.artists?.map((artist) => artist.name).filter(Boolean);
    if (albumArtists?.length) return albumArtists.join(', ');

    return 'Preview';
  }

  progressFill(): string {
    return `${this.player.progressPercent()}%`;
  }

  volumeFill(): string {
    return `${this.player.volumePercent()}%`;
  }

  formatTime(seconds: number): string {
    if (!Number.isFinite(seconds) || seconds <= 0) return '0:00';

    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }
}
