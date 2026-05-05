import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { KeyRound, LogIn, LucideAngularModule } from 'lucide-angular';

import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [FormsModule, LucideAngularModule, RouterLink],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss',
})
export class LoginComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  email = 'demo@musichub.local';
  password = 'password';
  twoFactorCode = '';

  readonly loading = signal(false);
  readonly error = signal('');
  readonly resendMessage = signal('');
  readonly challengeId = signal<string | null>(null);
  readonly debugCode = signal<string | null>(null);
  readonly icons = { KeyRound, LogIn };

  submit(): void {
    this.error.set('');
    this.resendMessage.set('');
    this.loading.set(true);

    this.auth.login(this.email, this.password).subscribe({
      next: (response) => {
        if ('requiresTwoFactor' in response) {
          this.challengeId.set(response.challengeId);
          this.debugCode.set(response.debugCode ?? null);
          this.resendMessage.set('');
          this.loading.set(false);
          return;
        }

        void this.router.navigate(['/']);
      },
      error: (error) => {
        this.error.set(this.errorMessage(error, 'No se ha podido iniciar sesion.'));
        this.loading.set(false);
      },
      complete: () => this.loading.set(false),
    });
  }

  verifyCode(): void {
    const challengeId = this.challengeId();
    const code = this.twoFactorCode.trim();

    if (!challengeId || code.length !== 6) {
      this.error.set('Introduce un codigo de 6 digitos.');
      return;
    }

    this.error.set('');
    this.resendMessage.set('');
    this.loading.set(true);

    this.auth.verifyTwoFactor(challengeId, code).subscribe({
      next: () => void this.router.navigate(['/']),
      error: (error) => {
        this.error.set(this.errorMessage(error, 'Codigo incorrecto o expirado.'));
        this.loading.set(false);
      },
      complete: () => this.loading.set(false),
    });
  }

  resendCode(): void {
    const challengeId = this.challengeId();

    if (!challengeId) {
      this.error.set('Vuelve a iniciar sesion para recibir otro codigo.');
      return;
    }

    this.error.set('');
    this.resendMessage.set('');
    this.loading.set(true);

    this.auth.resendTwoFactor(challengeId).subscribe({
      next: (response) => {
        if ('requiresTwoFactor' in response) {
          this.challengeId.set(response.challengeId);
          this.debugCode.set(response.debugCode ?? null);
          this.twoFactorCode = '';
          this.resendMessage.set('Te hemos enviado un codigo nuevo.');
        }
      },
      error: (error) => {
        this.error.set(this.errorMessage(error, 'No se ha podido reenviar el codigo.'));
        this.loading.set(false);
      },
      complete: () => this.loading.set(false),
    });
  }

  fillDemo(): void {
    this.email = 'demo@musichub.local';
    this.password = 'password';
    this.challengeId.set(null);
    this.debugCode.set(null);
    this.resendMessage.set('');
    this.twoFactorCode = '';
  }

  private errorMessage(error: any, fallback: string): string {
    const errors = error?.error?.errors;
    const firstError = errors ? Object.values(errors)[0] : null;

    if (Array.isArray(firstError) && firstError[0]) {
      return firstError[0];
    }

    return error?.error?.message ?? fallback;
  }
}
