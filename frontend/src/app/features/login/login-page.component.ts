import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { LockKeyhole, LogIn, LucideAngularModule, ShieldCheck } from 'lucide-angular';

import { AuthService } from '../../core/auth.service';
import { LoginStartResponse } from '../../core/models';

@Component({
  selector: 'app-login-page',
  imports: [FormsModule, LucideAngularModule],
  template: `
    <section class="auth-layout">
      <form class="auth-panel" (ngSubmit)="submit()">
        <span class="eyebrow">Acceso</span>
        <h1>{{ challenge() ? 'Verificacion 2FA' : 'Entrar en MusicHub' }}</h1>

        @if (!challenge()) {
          <label>
            Email
            <input name="email" type="email" [(ngModel)]="email" autocomplete="email" required />
          </label>
          <label>
            Password
            <input name="password" type="password" [(ngModel)]="password" autocomplete="current-password" required />
          </label>
        } @else {
          <label>
            Codigo
            <input name="code" type="text" maxlength="6" [(ngModel)]="code" autocomplete="one-time-code" required />
          </label>
          @if (challenge()?.debugCode) {
            <p class="hint">Codigo demo: {{ challenge()?.debugCode }}</p>
          }
        }

        @if (error()) {
          <p class="error-message">{{ error() }}</p>
        }

        <button class="primary-button primary-button--wide" type="submit" [disabled]="loading()">
          <lucide-icon [img]="challenge() ? icons.ShieldCheck : icons.LogIn" [size]="18"></lucide-icon>
          {{ loading() ? 'Procesando...' : challenge() ? 'Verificar' : 'Entrar' }}
        </button>

        <button class="ghost-button" type="button" (click)="fillDemo()">
          <lucide-icon [img]="icons.LockKeyhole" [size]="17"></lucide-icon>
          Usar demo
        </button>
      </form>
    </section>
  `,
})
export class LoginPageComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  email = 'demo@musichub.local';
  password = 'password';
  code = '';

  readonly challenge = signal<LoginStartResponse | null>(null);
  readonly loading = signal(false);
  readonly error = signal('');
  readonly icons = { LockKeyhole, LogIn, ShieldCheck };

  submit(): void {
    this.error.set('');
    this.loading.set(true);

    if (this.challenge()) {
      this.auth.verifyTwoFactor(this.challenge()!.challengeId, this.code).subscribe({
        next: () => void this.router.navigate(['/']),
        error: () => {
          this.error.set('Codigo incorrecto o expirado.');
          this.loading.set(false);
        },
      });
      return;
    }

    this.auth.login(this.email, this.password).subscribe({
      next: (response) => {
        if ('requiresTwoFactor' in response) {
          this.challenge.set(response);
          this.code = response.debugCode ?? '';
          this.loading.set(false);
          return;
        }

        void this.router.navigate(['/']);
      },
      error: () => {
        this.error.set('No se ha podido iniciar sesion.');
        this.loading.set(false);
      },
    });
  }

  fillDemo(): void {
    this.email = 'demo@musichub.local';
    this.password = 'password';
  }
}
