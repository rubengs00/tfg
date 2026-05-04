import { Injectable, signal } from '@angular/core';

import { Song } from './models';

@Injectable({ providedIn: 'root' })
export class PlayerService {
  private readonly audio = new Audio();

  readonly currentSong = signal<Song | null>(null);
  readonly isPlaying = signal(false);
  readonly volume = signal(0.8);
  readonly status = signal('');

  constructor() {
    this.audio.crossOrigin = 'anonymous';
    this.audio.preload = 'none';
    this.audio.volume = this.volume();
    this.audio.addEventListener('ended', () => this.isPlaying.set(false));
    this.audio.addEventListener('error', () => {
      this.isPlaying.set(false);
      this.status.set('No se pudo reproducir la preview.');
    });
  }

  play(song: Song): void {
    const isNewSong = this.currentSong()?.id !== song.id;
    this.currentSong.set(song);

    if (!song.previewUrl) {
      this.isPlaying.set(false);
      this.status.set('Spotify no ofrece preview para esta cancion.');
      return;
    }

    this.status.set('');

    if (isNewSong) {
      this.audio.src = song.previewUrl;
      this.audio.load();
    }

    void this.audio.play()
      .then(() => {
        this.isPlaying.set(true);
        this.status.set('');
      })
      .catch(() => {
        this.isPlaying.set(false);
        this.status.set('No se pudo reproducir la preview.');
      });
  }

  toggle(): void {
    const song = this.currentSong();

    if (!song) {
      this.status.set('Selecciona una cancion con preview para reproducirla.');
      return;
    }

    if (!song.previewUrl) {
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
}
