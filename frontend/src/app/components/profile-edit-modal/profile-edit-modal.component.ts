import { Component, EventEmitter, Output, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AuthService } from '../../core/auth.service';
import { User } from '../../core/models';
import { ProfileService } from '../../core/profile.service';

@Component({
  selector: 'app-profile-edit-modal',
  standalone: true,
  imports: [FormsModule],
  styleUrl: './profile-edit-modal.component.scss',
  templateUrl: './profile-edit-modal.component.html',
})
export class ProfileEditModalComponent {
  private readonly profileService = inject(ProfileService);
  readonly auth = inject(AuthService);

  @Output() closed = new EventEmitter<void>();

  readonly name = signal('');
  readonly avatarFile = signal<File | null>(null);
  readonly avatarPreview = signal<string | null>(null);
  readonly loadingProfile = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly busy = computed(() => this.loadingProfile() || this.saving());
  readonly avatarInitial = computed(() => (this.name().trim()[0] ?? 'U').toUpperCase());

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.avatarFile.set(file);

    const reader = new FileReader();
    reader.onload = () => this.avatarPreview.set(reader.result as string);
    reader.readAsDataURL(file);
  }

  onNameChange(value: string): void {
    this.name.set(value);
    this.error.set(null);
  }

  save(): void {
    const name = this.name().trim();

    if (!name) {
      this.error.set('El nombre no puede estar vacio.');
      return;
    }

    const formData = new FormData();
    formData.append('name', name);

    const avatar = this.avatarFile();
    if (avatar) {
      formData.append('avatar', avatar);
    }

    this.saving.set(true);
    this.error.set(null);

    this.profileService.updateProfile(formData).subscribe({
      next: ({ user }) => {
        this.auth.updateUser(user);
        this.saving.set(false);
        this.close();
      },
      error: (error) => {
        this.saving.set(false);
        this.error.set(error?.error?.message ?? 'No se pudo guardar el perfil.');
      },
    });
  }

  close(): void {
    document.body.classList.remove('modal-open');
    this.closed.emit();
  }

  ngOnInit(): void {
    document.body.classList.add('modal-open');
    this.loadBackendUser();
  }

  ngOnDestroy(): void {
    document.body.classList.remove('modal-open');
  }

  private loadBackendUser(): void {
    const cachedUser = this.auth.user();
    if (cachedUser) {
      this.fillForm(cachedUser);
    }

    this.loadingProfile.set(true);
    this.error.set(null);

    this.auth.refreshMe().subscribe({
      next: (user) => {
        this.fillForm(user);
        this.loadingProfile.set(false);
      },
      error: () => {
        this.loadingProfile.set(false);

        if (!cachedUser) {
          this.error.set('No se pudo cargar el perfil desde el backend.');
        }
      },
    });
  }

  private fillForm(user: User): void {
    this.name.set(user.name ?? '');
    this.avatarPreview.set(user.avatarUrl ?? null);
    this.avatarFile.set(null);
  }
}



