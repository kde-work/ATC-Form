import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthTokenService } from '../services/auth-token.service';

/** Добавляет Bearer-токен админки ко всем исходящим запросам. */
export const authTokenInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthTokenService);
  const token = auth.token();

  if (!token) {
    return next(req);
  }

  return next(
    req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
    }),
  );
};
