import { Component, effect, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { LibraryService } from '../../services/library.service';
import { Playlist } from '../../interfaces/music.interfaces';

@Component({
  selector: 'app-playlist-edit-modal',
  standalone: true,
  imports: [FormsModule],
  styleUrl: './playlist-edit-modal.component.scss',
  templateUrl: './playlist-edit-modal.component.html',
})
export class PlaylistEditModalComponent {
  private readonly library = inject(LibraryService);
  readonly playlist = input<Playlist | null>(null);
  readonly closed = output<void>();
  readonly updated = output<Playlist>();

  name = '';
  description = '';

  readonly coverFile = signal<File | null>(null);
  readonly coverPreview = signal<string | null>(null);
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  private readonly syncPlaylist = effect(() => {
    const playlist = this.playlist();

    if (!playlist) return;

    this.name = playlist.name ?? '';
    this.description = playlist.description ?? '';
    this.coverPreview.set(playlist.coverUrl ?? null);
    this.coverFile.set(null);
    this.error.set(null);
  });

  coverInitial(): string {
    return (this.name.trim()[0] ?? 'P').toUpperCase();
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.coverFile.set(file);
    this.error.set(null);

    const reader = new FileReader();
    reader.onload = () => this.coverPreview.set(reader.result as string);
    reader.readAsDataURL(file);
  }

  save(): void {
    const playlist = this.playlist();
    const name = this.name.trim();

    if (!playlist || !name) {
      this.error.set('El nombre no puede estar vacio.');
      return;
    }

    const formData = new FormData();
    formData.append('name', name);
    formData.append('description', this.description.trim());

    const cover = this.coverFile();
    if (cover) {
      formData.append('cover', cover);
    }

    this.loading.set(true);
    this.error.set(null);

    this.library.updatePlaylist(playlist.id, formData).subscribe({
      next: (updated) => {
        this.loading.set(false);
        this.updated.emit(updated);
        this.close();
      },
      error: (error) => {
        this.loading.set(false);
        this.error.set(error?.error?.message ?? 'No se pudo guardar la playlist.');
      },
    });
  }

  close(): void {
    document.body.classList.remove('modal-open');
    this.closed.emit();
  }

  ngOnInit(): void {
    document.body.classList.add('modal-open');
  }

  ngOnDestroy(): void {
    document.body.classList.remove('modal-open');
  }
}
