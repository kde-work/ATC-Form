import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthTokenService } from '../services/auth-token.service';

/** Без токена редирект на /admin/login. */
export const adminAuthGuard: CanActivateFn = () => {
  const auth = inject(AuthTokenService);
  const router = inject(Router);

  if (auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/admin/login']);
};

/** Уже авторизованного уводит с login на imports. */
export const adminGuestGuard: CanActivateFn = () => {
  const auth = inject(AuthTokenService);
  const router = inject(Router);

  if (!auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/admin/imports']);
};
