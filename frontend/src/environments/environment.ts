/**
 * Production: API с того же origin через nginx.
 */
export const environment = {
  production: true,
  apiBaseUrl: '/api/v1',
  /** Совпадает с default TARIFF_IMPORT_MAX_BYTES на backend. */
  tariffImportMaxBytes: 5_242_880,
};
