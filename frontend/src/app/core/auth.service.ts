import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';

import { API_BASE_URL } from './api';
import { SessionResponse, User } from './models';

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
    return this.http.post<SessionResponse>(`${API_BASE_URL}/auth/login`, {
      email,
      password,
    }).pipe(tap((response) => this.setSession(response)));
  }

  register(name: string, email: string, password: string) {
    return this.http.post<SessionResponse>(`${API_BASE_URL}/auth/register`, {
      name,
      email,
      password,
    }).pipe(tap((response) => this.setSession(response)));
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
