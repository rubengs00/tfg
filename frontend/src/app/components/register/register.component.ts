import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { KeyRound, LucideAngularModule, UserPlus } from 'lucide-angular';

import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-register',
  imports: [FormsModule, LucideAngularModule, RouterLink],
  templateUrl: './register.component.html',
  styleUrl: './register.component.scss',
})
export class RegisterComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  name = '';
  email = '';
  password = '';
  passwordConfirm = '';
  twoFactorCode = '';

  readonly loading = signal(false);
  readonly error = signal('');
  readonly resendMessage = signal('');
  readonly fieldErrors = signal<Record<string, string>>({});
  readonly challengeId = signal<string | null>(null);
  readonly debugCode = signal<string | null>(null);
  readonly icons = { KeyRound, UserPlus };

  submit(): void {
    const validationErrors = this.validateForm();

    if (Object.keys(validationErrors).length) {
      this.fieldErrors.set(validationErrors);
      this.error.set('Revisa los campos marcados.');
      return;
    }

    this.error.set('');
    this.resendMessage.set('');
    this.fieldErrors.set({});
    this.loading.set(true);

    this.auth.register(this.name, this.email, this.password, this.passwordConfirm).subscribe({
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
        this.applyBackendErrors(error);
        this.error.set(this.errorMessage(error, 'No se ha podido crear la cuenta.'));
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
      this.error.set('Vuelve a crear la cuenta para recibir otro codigo.');
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

  private errorMessage(error: any, fallback: string): string {
    const errors = error?.error?.errors;
    const firstError = errors ? Object.values(errors)[0] : null;

    if (Array.isArray(firstError) && firstError[0]) {
      return firstError[0];
    }

    return error?.error?.message ?? fallback;
  }

  private validateForm(): Record<string, string> {
    const errors: Record<string, string> = {};
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (this.name.trim().length < 2) {
      errors['name'] = 'El nombre debe tener al menos 2 caracteres.';
    }

    if (!emailPattern.test(this.email.trim())) {
      errors['email'] = 'Introduce un email valido.';
    }

    if (this.password.length < 8) {
      errors['password'] = 'La password debe tener al menos 8 caracteres.';
    } else if (!/[A-Za-z]/.test(this.password)) {
      errors['password'] = 'La password debe incluir al menos una letra.';
    } else if (!/\d/.test(this.password)) {
      errors['password'] = 'La password debe incluir al menos un numero.';
    } else if (this.password !== this.passwordConfirm) {
      errors['password'] = 'Las passwords no coinciden.';
    }

    return errors;
  }

  private applyBackendErrors(error: any): void {
    const errors = error?.error?.errors;

    if (!errors) {
      return;
    }

    const mappedErrors: Record<string, string> = {};

    for (const [field, messages] of Object.entries(errors)) {
      if (Array.isArray(messages) && messages[0]) {
        mappedErrors[field] = messages[0] as string;
      }
    }

    this.fieldErrors.set(mappedErrors);
  }

  fieldError(field: string): string {
    return this.fieldErrors()[field] ?? '';
  }

  clearFieldError(field: string): void {
    const errors = { ...this.fieldErrors() };
    delete errors[field];
    this.fieldErrors.set(errors);
    this.error.set('');
  }
}



