import { Component, EventEmitter, Output, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AuthService } from '../../core/auth.service';
import { ProfileService } from '../../core/profile.service';

@Component({
  selector: 'app-profile-edit-modal',
  standalone: true,
  imports: [FormsModule],
  styleUrls: ['../library/playlist-edit-modal.component.scss'],
  template: `
    <div class="spotify-modal-overlay" (click)="close()">
      <div class="spotify-modal" (click)="$event.stopPropagation()">
        <div class="edit-header">
          <h2>Editar información</h2>
          <button class="close-btn" (click)="close()">×</button>
        </div>

        <div class="edit-body">
          <div class="edit-image-block">
            <img
              [src]="avatarPreview() ?? auth.user()?.avatar_url ?? ''"
              class="edit-image"
            />
            <label class="image-picker">
              Elegir foto
              <input type="file" accept="image/*" (change)="onFileSelected($event)" hidden />
            </label>
          </div>

          <div class="edit-fields">
            <input
              [ngModel]="name()"
              (ngModelChange)="onNameChange($event)"
              placeholder="Nombre"
            />
          </div>
        </div>

        <div class="edit-footer">
          <button class="save-btn" (click)="save()" [disabled]="loading()">
            {{ loading() ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  `,
})
export class ProfileEditModalComponent {
  private readonly profileService = inject(ProfileService);
  readonly auth = inject(AuthService);

  @Output() closed = new EventEmitter<void>();

  readonly name = signal('');
  readonly avatarFile = signal<File | null>(null);
  readonly avatarPreview = signal<string | null>(null);
  readonly loading = signal(false);

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.avatarFile.set(file);

    const reader = new FileReader();
    reader.onload = () => {
      const preview = reader.result as string;
      this.avatarPreview.set(preview);

      // ✅ actualización inmediata visual
      const current = this.auth.user();
      if (current) {
        this.auth.updateUser({
          ...current,
          avatar_url: preview,
        });
      }
    };
    reader.readAsDataURL(file);
  }

  onNameChange(value: string): void {
    this.name.set(value);

    // ✅ actualización visual inmediata del nombre
    const current = this.auth.user();
    if (current) {
      this.auth.updateUser({
        ...current,
        name: value,
      });
    }
  }

  save(): void {
    const formData = new FormData();
    formData.append('name', this.name());

    if (this.avatarFile()) {
      formData.append('avatar', this.avatarFile() as File);
    }

    this.loading.set(true);

    this.profileService.updateProfile(formData).subscribe({
      next: (response: any) => {
        this.loading.set(false);
        if (response.user) {
          this.auth.updateUser({
            ...response.user,
            avatar_url: response.user.avatar_url ? response.user.avatar_url + '?v=' + Date.now() : null,
          });
        }
        this.close();
      },
      error: () => this.loading.set(false),
    });
  }

  close(): void {
    document.body.classList.remove('modal-open');
    this.closed.emit();
  }

  ngOnInit(): void {
    document.body.classList.add('modal-open');

    const user = this.auth.user();
    if (user) {
      this.name.set(user.name ?? '');
      this.avatarPreview.set(user.avatar_url ?? null);
    }
  }

  ngOnDestroy(): void {
    document.body.classList.remove('modal-open');
  }
}
