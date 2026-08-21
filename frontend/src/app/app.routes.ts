import { Routes } from '@angular/router';
import { adminAuthGuard } from './core/guards/admin-auth.guard';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'calculator' },
  {
    path: 'calculator',
    loadComponent: () =>
      import('./features/calculator/calculator-page').then((m) => m.CalculatorPage),
  },
  {
    path: 'admin/login',
    loadComponent: () =>
      import('./features/admin/login/admin-login-page').then((m) => m.AdminLoginPage),
  },
  {
    path: 'admin',
    canActivate: [adminAuthGuard],
    children: [
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'imports',
      },
      {
        path: '**',
        loadComponent: () =>
          import('./features/admin/placeholder/admin-placeholder-page').then(
            (m) => m.AdminPlaceholderPage,
          ),
      },
    ],
  },
  { path: '**', redirectTo: 'calculator' },
];
