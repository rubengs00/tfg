import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';

import { API_BASE_URL } from '../config/api.config';
import { AuthResponse, SessionResponse, User } from '../interfaces/music.interfaces';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly tokenState = signal<string | null>(globalThis.localStorage?.getItem('musichub_token') ?? null);
  private readonly userState = signal<User | null>(this.readStoredUser());

  readonly token = this.tokenState.asReadonly();
  readonly user = this.userState.asReadonly();
  readonly isLoggedIn = computed(() => Boolean(this.tokenState() && this.userState()));
  readonly isAdmin = computed(() => this.userState()?.role === 'admin');

  constructor() {
    if (this.tokenState()) {
      this.refreshMe().subscribe({ error: () => undefined });
    }
  }

  login(email: string, password: string) {
    return this.http.post<AuthResponse>(`${API_BASE_URL}/auth/login`, {
      email,
      password,
    }).pipe(tap((response) => this.storeSessionIfPresent(response)));
  }

  register(name: string, email: string, password: string, passwordConfirmation: string) {
    return this.http.post<AuthResponse>(`${API_BASE_URL}/auth/register`, {
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    }).pipe(tap((response) => this.storeSessionIfPresent(response)));
  }

  verifyTwoFactor(challengeId: string, code: string) {
    return this.http.post<SessionResponse>(`${API_BASE_URL}/auth/verify-2fa`, {
      challengeId,
      code,
    }).pipe(tap((response) => this.setSession(response)));
  }

  resendTwoFactor(challengeId: string) {
    return this.http.post<AuthResponse>(`${API_BASE_URL}/auth/resend-2fa`, {
      challengeId,
    });
  }

  refreshMe() {
    return this.http.get<User>(`${API_BASE_URL}/auth/me`).pipe(
      tap((user) => {
        this.userState.set(user);
        globalThis.localStorage?.setItem('musichub_user', JSON.stringify(user));
      }),
    );
  }

  logout() {
    return this.http.post(`${API_BASE_URL}/auth/logout`, {}).pipe(tap(() => this.clearSession()));
  }

  clearSession(): void {
    this.tokenState.set(null);
    this.userState.set(null);
    globalThis.localStorage?.removeItem('musichub_token');
    globalThis.localStorage?.removeItem('musichub_user');
  }

  private setSession(response: SessionResponse): void {
    const token = response.token ?? null;
    this.tokenState.set(token);
    this.userState.set(response.user);

    if (token) {
      globalThis.localStorage?.setItem('musichub_token', token);
    } else {
      globalThis.localStorage?.removeItem('musichub_token');
    }

    globalThis.localStorage?.setItem('musichub_user', JSON.stringify(response.user));
  }

  private storeSessionIfPresent(response: AuthResponse): void {
    if ('token' in response) {
      this.setSession(response);
    }
  }

  updateUser(user: User): void {
    this.userState.set(user);
    globalThis.localStorage?.setItem('musichub_user', JSON.stringify(user));
  }

  private readStoredUser(): User | null {
    const value = globalThis.localStorage?.getItem('musichub_user');

    if (!value) {
      return null;
    }

    try {
      return JSON.parse(value) as User;
    } catch {
      return null;
    }
  }
}
