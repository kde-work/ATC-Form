/**
 * Development: ng serve + proxy на Laravel (см. proxy.conf.json).
 */
export const environment = {
  production: false,
  apiBaseUrl: '/api/v1',
  /** Совпадает с ADMIN_PATH в .env backend. */
  adminPath: 'cp-x7k9m2p4w8',
  /** Совпадает с default TARIFF_IMPORT_MAX_BYTES на backend. */
  tariffImportMaxBytes: 5_242_880,
};
