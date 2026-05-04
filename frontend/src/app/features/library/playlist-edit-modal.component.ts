import { Component, EventEmitter, Input, Output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { Playlist } from '../../core/models';

@Component({
  selector: 'app-playlist-edit-modal',
  standalone: true,
  imports: [FormsModule],
  styleUrls: ['./playlist-edit-modal.component.scss'],
  template: `
    <div class="spotify-modal-overlay" (click)="close()">
      <div class="spotify-modal" (click)="$event.stopPropagation()">
        <div class="edit-header">
          <h2>Editar información</h2>
          <button class="close-btn" type="button" (click)="close()">×</button>
        </div>

        <div class="edit-body">
          <div class="edit-image-block">
            <img [src]="coverPreview() ?? playlist?.coverUrl ?? ''" class="edit-image" />
            <label class="image-picker">
              Elegir foto
              <input type="file" accept="image/*" (change)="onFileSelected($event)" hidden />
            </label>
          </div>

          <div class="edit-fields">
            <input [(ngModel)]="name" placeholder="Nombre" />
            <textarea [(ngModel)]="description" placeholder="Descripción"></textarea>
          </div>
        </div>

        <div class="edit-footer">
          <button class="save-btn" type="button" (click)="save()" [disabled]="loading()">
            {{ loading() ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  `,
})
export class PlaylistEditModalComponent {
  private _playlist: Playlist | null = null;

  @Input()
  set playlist(value: Playlist | null) {
    this._playlist = value;

    if (!value) return;

    this.name = value.name ?? '';
    this.description = value.description ?? '';
    this.coverPreview.set(value.coverUrl ?? null);
    this.coverFile.set(null);
  }

  get playlist(): Playlist | null {
    return this._playlist;
  }

  @Output() closed = new EventEmitter<void>();

  /**
   * Emitimos cambios al padre; el padre se encarga de llamar a LibraryService.updatePlaylist(...)
   * para mantener el componente desacoplado del API.
   */
  @Output() updated = new EventEmitter<{ playlist: Playlist; coverFile: File | null }>();

  name = '';
  description = '';

  readonly coverFile = signal<File | null>(null);
  readonly coverPreview = signal<string | null>(null);
  readonly loading = signal(false);

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.coverFile.set(file);

    const reader = new FileReader();
    reader.onload = () => this.coverPreview.set(reader.result as string);
    reader.readAsDataURL(file);
  }

  save(): void {
    const playlist = this.playlist;
    if (!playlist) return;

    const name = this.name.trim();
    const description = this.description.trim();

    if (!name) return;

    const patch: Playlist = {
      ...playlist,
      name,
      description,
      // coverUrl aquí es solo para previsualización; el backend la devolverá al guardar.
      coverUrl: this.coverPreview(),
    };

    this.loading.set(true);
    // el padre hará la llamada real; aquí solo emitimos.
    this.updated.emit({ playlist: patch, coverFile: this.coverFile() });
    this.loading.set(false);

    this.close();
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
