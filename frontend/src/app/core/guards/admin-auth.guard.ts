import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { adminRoute } from '../constants/admin-path';
import { AuthTokenService } from '../services/auth-token.service';

/** Без токена редирект на login admin-панели. */
export const adminAuthGuard: CanActivateFn = () => {
  const auth = inject(AuthTokenService);
  const router = inject(Router);

  if (auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree([adminRoute('login')]);
};

/** Уже авторизованного уводит с login на imports. */
export const adminGuestGuard: CanActivateFn = () => {
  const auth = inject(AuthTokenService);
  const router = inject(Router);

  if (!auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree([adminRoute('imports')]);
};
