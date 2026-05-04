import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';

import { API_BASE_URL } from './api';
import { LoginStartResponse, SessionResponse, User } from './models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly tokenState = signal<string | null>(globalThis.localStorage?.getItem('musichub_token') ?? null);
  private readonly userState = signal<User | null>(this.readStoredUser());

  readonly token = this.tokenState.asReadonly();
  readonly user = this.userState.asReadonly();
  readonly isLoggedIn = computed(() => Boolean(this.tokenState() && this.userState()));
  readonly isAdmin = computed(() => this.userState()?.role === 'admin');

  login(email: string, password: string) {
    return this.http.post<LoginStartResponse | SessionResponse>(`${API_BASE_URL}/auth/login`, {
      email,
      password,
    }).pipe(tap((response) => this.storeSessionIfPresent(response)));
  }

  register(name: string, email: string, password: string) {
    return this.http.post<LoginStartResponse>(`${API_BASE_URL}/auth/register`, { name, email, password });
  }

  verifyTwoFactor(challengeId: string, code: string) {
    return this.http.post<SessionResponse>(`${API_BASE_URL}/auth/verify-2fa`, {
      challengeId,
      code,
    }).pipe(tap((response) => this.setSession(response)));
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

  private storeSessionIfPresent(response: LoginStartResponse | SessionResponse): void {
    if ('token' in response) {
      this.setSession(response);
    }
  }

  private setSession(response: SessionResponse): void {
    this.tokenState.set(response.token);
    this.userState.set(response.user);
    globalThis.localStorage?.setItem('musichub_token', response.token);
    globalThis.localStorage?.setItem('musichub_user', JSON.stringify(response.user));
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
