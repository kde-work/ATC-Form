import { environment } from '../../../environments/environment';

/** Секретный префикс UI и API admin-панели (совпадает с ADMIN_PATH в .env). */
export const adminPath = environment.adminPath;

/** Абсолютный путь в SPA: /{adminPath}/... */
export function adminRoute(...segments: (string | number)[]): string {
  const tail = segments.map(String).filter(Boolean).join('/');
  return tail ? `/${adminPath}/${tail}` : `/${adminPath}`;
}

/** Базовый URL admin API: /api/v1/{adminPath}. */
export function adminApiBase(apiBaseUrl: string): string {
  return `${apiBaseUrl}/${adminPath}`;
}
