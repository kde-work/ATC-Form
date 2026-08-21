import { Injectable, signal } from '@angular/core';

const STORAGE_KEY = 'atc_admin_token';

/**
 * Хранение Bearer-токена админки.
 * Полный login — этап 10; здесь только контракт для guard.
 */
@Injectable({ providedIn: 'root' })
export class AuthTokenService {
  private readonly tokenSignal = signal<string | null>(this.readStored());

  readonly token = this.tokenSignal.asReadonly();

  isAuthenticated(): boolean {
    const value = this.tokenSignal();
    return value !== null && value !== '';
  }

  setToken(token: string): void {
    localStorage.setItem(STORAGE_KEY, token);
    this.tokenSignal.set(token);
  }

  clearToken(): void {
    localStorage.removeItem(STORAGE_KEY);
    this.tokenSignal.set(null);
  }

  private readStored(): string | null {
    try {
      const value = localStorage.getItem(STORAGE_KEY);
      return value !== null && value !== '' ? value : null;
    } catch {
      return null;
    }
  }
}
