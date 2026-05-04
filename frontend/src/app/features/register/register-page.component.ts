import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { LucideAngularModule, UserPlus } from 'lucide-angular';

import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-register-page',
  imports: [FormsModule, LucideAngularModule, RouterLink],
  template: `
    <section class="auth-layout">
      <form class="auth-panel" (ngSubmit)="submit()">
        <span class="eyebrow">Registro</span>
        <h1>Crear cuenta</h1>

        <label>
          Nombre
          <input name="name" [(ngModel)]="name" autocomplete="name" required />
        </label>

        <label>
          Email
          <input name="email" type="email" [(ngModel)]="email" autocomplete="email" required />
        </label>

        <label>
          Password
          <input name="password" type="password" [(ngModel)]="password" autocomplete="new-password" required />
        </label>

        <label>
          Repetir password
          <input
            name="passwordConfirm"
            type="password"
            [(ngModel)]="passwordConfirm"
            autocomplete="new-password"
            required
          />
        </label>

        @if (error()) {
          <p class="error-message">{{ error() }}</p>
        }

        <button class="primary-button primary-button--wide" type="submit" [disabled]="loading()">
          <lucide-icon [img]="icons.UserPlus" [size]="18"></lucide-icon>
          {{ loading() ? 'Creando cuenta...' : 'Crear cuenta' }}
        </button>

        <p class="hint auth-link">
          ¿Ya tienes cuenta? <a routerLink="/login">Inicia sesion</a>
        </p>
      </form>
    </section>
  `,
})
export class RegisterPageComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  name = '';
  email = '';
  password = '';
  passwordConfirm = '';

  readonly loading = signal(false);
  readonly error = signal('');
  readonly icons = { UserPlus };

  submit(): void {
    if (this.password !== this.passwordConfirm) {
      this.error.set('Las passwords no coinciden.');
      return;
    }

    this.error.set('');
    this.loading.set(true);

    this.auth.register(this.name, this.email, this.password).subscribe({
      next: () => void this.router.navigate(['/']),
      error: () => {
        this.error.set('No se ha podido crear la cuenta.');
        this.loading.set(false);
      },
      complete: () => this.loading.set(false),
    });
  }
}
