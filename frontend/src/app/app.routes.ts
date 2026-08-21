import { Routes } from '@angular/router';
import { adminAuthGuard, adminGuestGuard } from './core/guards/admin-auth.guard';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'calculator' },
  {
    path: 'calculator',
    loadComponent: () =>
      import('./features/calculator/calculator-page').then((m) => m.CalculatorPage),
  },
  {
    path: 'admin/login',
    canActivate: [adminGuestGuard],
    loadComponent: () =>
      import('./features/admin/login/admin-login-page').then((m) => m.AdminLoginPage),
  },
  {
    path: 'admin',
    canActivate: [adminAuthGuard],
    loadComponent: () =>
      import('./features/admin/shell/admin-shell').then((m) => m.AdminShell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'imports' },
      {
        path: 'imports',
        loadComponent: () =>
          import('./features/admin/imports/imports-list-page').then((m) => m.ImportsListPage),
      },
      {
        path: 'imports/new',
        loadComponent: () =>
          import('./features/admin/imports/import-new-page').then((m) => m.ImportNewPage),
      },
      {
        path: 'imports/:id',
        loadComponent: () =>
          import('./features/admin/imports/import-detail-page').then((m) => m.ImportDetailPage),
      },
      {
        path: 'tariffs',
        loadComponent: () =>
          import('./features/admin/tariffs/tariffs-page').then((m) => m.TariffsPage),
      },
      {
        path: 'settings',
        loadComponent: () =>
          import('./features/admin/settings/settings-page').then((m) => m.SettingsPage),
      },
    ],
  },
  { path: '**', redirectTo: 'calculator' },
];
