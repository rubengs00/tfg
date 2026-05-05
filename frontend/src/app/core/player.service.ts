import { computed, Injectable, signal } from '@angular/core';

import { SpotifyTrack } from './models';

@Injectable({ providedIn: 'root' })
export class PlayerService {
  private readonly audio = new Audio();

  readonly currentSong = signal<SpotifyTrack | null>(null);
  readonly isPlaying = signal(false);
  readonly isLoading = signal(false);
  readonly isMuted = signal(false);
  readonly volume = signal(0.8);
  readonly status = signal('');
  readonly progress = signal(0);
  readonly duration = signal(0);
  readonly canSeek = computed(() => this.duration() > 0);
  readonly progressPercent = computed(() => {
    const duration = this.duration();
    if (duration <= 0) return 0;

    return Math.min(100, Math.max(0, (this.progress() / duration) * 100));
  });
  readonly volumePercent = computed(() => (this.isMuted() ? 0 : this.volume()) * 100);

  constructor() {
    this.audio.preload = 'metadata';
    this.audio.volume = this.volume();

    this.audio.addEventListener('loadstart', () => {
      if (!this.currentSong()) return;

      this.isLoading.set(true);
      this.status.set('Cargando preview...');
    });

    this.audio.addEventListener('ended', () => {
      this.audio.currentTime = 0;
      this.isPlaying.set(false);
      this.isLoading.set(false);
      this.progress.set(0);
    });

    this.audio.addEventListener('play', () => {
      this.isPlaying.set(true);
    });

    this.audio.addEventListener('pause', () => {
      this.isPlaying.set(false);
    });

    this.audio.addEventListener('playing', () => {
      this.isPlaying.set(true);
      this.isLoading.set(false);
      this.status.set('');
    });

    this.audio.addEventListener('waiting', () => {
      if (this.audio.paused) return;

      this.isLoading.set(true);
      this.status.set('Cargando preview...');
    });

    this.audio.addEventListener('canplay', () => {
      this.isLoading.set(false);
      if (this.status() === 'Cargando preview...') {
        this.status.set('');
      }
    });

    this.audio.addEventListener('timeupdate', () => {
      this.progress.set(this.audio.currentTime);
    });

    this.audio.addEventListener('loadedmetadata', () => {
      this.duration.set(this.getFiniteDuration());
    });

    this.audio.addEventListener('durationchange', () => {
      this.duration.set(this.getFiniteDuration());
    });

    this.audio.addEventListener('error', () => {
      if (!this.currentSong()) return;

      this.isPlaying.set(false);
      this.isLoading.set(false);
      this.status.set('No se pudo reproducir la preview.');
    });
  }

  play(track: SpotifyTrack): void {
    const current = this.currentSong();
    const previewUrl = track.preview_url?.trim();

    if (!previewUrl) {
      this.audio.pause();
      this.audio.currentTime = 0;
      this.audio.removeAttribute('src');
      this.currentSong.set(track);
      this.isPlaying.set(false);
      this.isLoading.set(false);
      this.progress.set(0);
      this.duration.set(0);
      this.status.set('Spotify no ofrece preview para esta cancion.');
      return;
    }

    if (current && current.id === track.id) {
      this.toggle();
      return;
    }

    this.audio.pause();
    this.audio.currentTime = 0;

    this.currentSong.set(track);
    this.status.set('Cargando preview...');
    this.isLoading.set(true);
    this.progress.set(0);
    this.duration.set(0);

    this.audio.src = previewUrl;
    this.audio.load();

    this.audio
      .play()
      .then(() => {
        this.isPlaying.set(true);
        this.isLoading.set(false);
        this.status.set('');
      })
      .catch((err) => {
        console.error('Audio play error:', err);
        this.isPlaying.set(false);
        this.isLoading.set(false);
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
      if (this.audio.ended || (this.duration() > 0 && this.audio.currentTime >= this.duration())) {
        this.audio.currentTime = 0;
        this.progress.set(0);
      }

      this.isLoading.set(true);
      this.status.set('Cargando preview...');
      void this.audio
        .play()
        .then(() => {
          this.isPlaying.set(true);
          this.isLoading.set(false);
          this.status.set('');
        })
        .catch(() => {
          this.isPlaying.set(false);
          this.isLoading.set(false);
          this.status.set('No se pudo reproducir la preview.');
        });
      return;
    }

    this.audio.pause();
    this.isPlaying.set(false);
    this.isLoading.set(false);
  }

  setVolume(value: number): void {
    const nextVolume = Math.min(1, Math.max(0, value));
    this.volume.set(nextVolume);
    this.audio.volume = nextVolume;

    if (nextVolume === 0) {
      this.isMuted.set(true);
      this.audio.muted = true;
      return;
    }

    if (this.isMuted()) {
      this.isMuted.set(false);
      this.audio.muted = false;
    }
  }

  toggleMute(): void {
    const nextMuted = !this.isMuted();
    this.isMuted.set(nextMuted);
    this.audio.muted = nextMuted;

    if (!nextMuted && this.volume() === 0) {
      this.setVolume(0.7);
    }
  }

  seek(seconds: number | string): void {
    if (!this.canSeek()) return;

    const requestedSeconds = Number(seconds);
    if (Number.isNaN(requestedSeconds)) return;

    const clamped = Math.min(Math.max(0, requestedSeconds), this.duration());
    this.audio.currentTime = clamped;
    this.progress.set(clamped);
  }

  clear(): void {
    this.audio.pause();
    this.currentSong.set(null);
    this.isPlaying.set(false);
    this.isLoading.set(false);
    this.status.set('');
    this.progress.set(0);
    this.duration.set(0);
    this.audio.removeAttribute('src');
    this.audio.load();
  }

  private getFiniteDuration(): number {
    return Number.isFinite(this.audio.duration) ? this.audio.duration : 0;
  }
}
