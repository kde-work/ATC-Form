import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { ApiClientError } from '../models/api.models';
import { AdminAuthService } from '../services/admin-auth.service';

/**
 * При 401 очищает сессию и уводит на login.
 * Ставится после apiErrorInterceptor, чтобы получать ApiClientError.
 */
export const unauthorizedInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AdminAuthService);
  const router = inject(Router);

  return next(req).pipe(
    catchError((error: unknown) => {
      if (
        error instanceof ApiClientError &&
        error.status === 401 &&
        !req.url.includes('/admin/login')
      ) {
        auth.clearSession();
        void router.navigateByUrl('/admin/login');
      }

      return throwError(() => error);
    }),
  );
};
