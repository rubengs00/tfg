import { Injectable, signal } from '@angular/core';

import { SpotifyTrack } from './models';

@Injectable({ providedIn: 'root' })
export class PlayerService {
  private readonly audio = new Audio();

  readonly currentSong = signal<SpotifyTrack | null>(null);
  readonly isPlaying = signal(false);
  readonly volume = signal(0.8);
  readonly status = signal('');
  readonly progress = signal(0);
  readonly duration = signal(0);

  constructor() {
    this.audio.crossOrigin = 'anonymous';
    this.audio.preload = 'none';
    this.audio.volume = this.volume();
    this.audio.addEventListener('ended', () => {
      this.isPlaying.set(false);
      this.progress.set(0);
    });

    this.audio.addEventListener('timeupdate', () => {
      this.progress.set(this.audio.currentTime);
    });

    this.audio.addEventListener('loadedmetadata', () => {
      this.duration.set(this.audio.duration || 0);
    });

    this.audio.addEventListener('error', () => {
      this.isPlaying.set(false);
      this.status.set('No se pudo reproducir la preview.');
    });
  }

  play(track: SpotifyTrack): void {
    const current = this.currentSong();

    if (!track.preview_url) {
      this.isPlaying.set(false);
      this.status.set('Spotify no ofrece preview para esta cancion.');
      return;
    }

    // Same track → toggle
    if (current && current.id === track.id) {
      if (this.audio.paused) {
        void this.audio.play().then(() => {
          this.isPlaying.set(true);
        });
      } else {
        this.audio.pause();
        this.isPlaying.set(false);
      }
      return;
    }

    // New track
    this.audio.pause();
    this.audio.currentTime = 0;

    this.currentSong.set(track);
    this.status.set('');
    this.progress.set(0);

    this.audio.src = track.preview_url;
    this.audio.load();

    this.audio.play()
      .then(() => {
        this.isPlaying.set(true);
      })
      .catch((err) => {
        console.error('Audio play error:', err);
        this.isPlaying.set(false);
        this.status.set('No se pudo reproducir la preview.');
      });
  }

  toggle(): void {
    const track = this.currentSong();

    if (!track) {
      this.status.set('Selecciona una cancion con preview para reproducirla.');
      return;
    }

    if (!track.preview_url) {
      this.isPlaying.set(false);
      this.status.set('Spotify no ofrece preview para esta cancion.');
      return;
    }

    if (this.audio.paused) {
      void this.audio.play()
        .then(() => {
          this.isPlaying.set(true);
          this.status.set('');
        })
        .catch(() => {
          this.isPlaying.set(false);
          this.status.set('No se pudo reproducir la preview.');
        });
      return;
    }

    this.audio.pause();
    this.isPlaying.set(false);
  }

  setVolume(value: number): void {
    const nextVolume = Math.min(1, Math.max(0, value));
    this.volume.set(nextVolume);
    this.audio.volume = nextVolume;
  }

  seek(seconds: number): void {
    const clamped = Math.min(Math.max(0, seconds), this.duration());
    this.audio.currentTime = clamped;
    this.progress.set(clamped);
  }
}
