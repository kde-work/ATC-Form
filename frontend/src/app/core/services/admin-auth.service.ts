import { HttpClient } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import { adminApiBase } from '../constants/admin-path';
import { AdminLoginResponseDto, AdminUserDto } from '../models/api.models';
import { AdminQueryCache } from './admin-query-cache';
import { AuthTokenService } from './auth-token.service';

/** Login / logout / me для админки. */
@Injectable({ providedIn: 'root' })
export class AdminAuthService {
  private readonly http = inject(HttpClient);
  private readonly tokens = inject(AuthTokenService);
  private readonly cache = inject(AdminQueryCache);
  private readonly baseUrl = adminApiBase(environment.apiBaseUrl);

  private readonly userSignal = signal<AdminUserDto | null>(null);
  readonly user = this.userSignal.asReadonly();

  isAuthenticated(): boolean {
    return this.tokens.isAuthenticated();
  }

  login(email: string, password: string): Observable<AdminLoginResponseDto> {
    return this.http
      .post<AdminLoginResponseDto>(`${this.baseUrl}/login`, { email, password })
      .pipe(
        tap((response) => {
          this.cache.clear();
          this.tokens.setToken(response.token);
          this.userSignal.set(response.user);
          this.cache.set('me', response.user);
        }),
      );
  }

  logout(): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.baseUrl}/logout`, {}).pipe(
      tap({
        next: () => this.clearSession(),
        error: () => this.clearSession(),
      }),
    );
  }

  me(): Observable<AdminUserDto> {
    return this.cache
      .getOrLoad('me', () => this.http.get<AdminUserDto>(`${this.baseUrl}/me`))
      .pipe(tap((user) => this.userSignal.set(user)));
  }

  clearSession(): void {
    this.tokens.clearToken();
    this.userSignal.set(null);
    this.cache.clear();
  }
}
