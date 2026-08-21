import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { catchError, forkJoin, map, Observable, of, tap } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  AdminSettingsDto,
  AdminTariffDto,
  ImportStatus,
  PaginatedDto,
  PlatformCode,
  TariffImportDetailDto,
  TariffImportListItemDto,
} from '../models/api.models';
import { AdminQueryCache } from './admin-query-cache';
import { CalculatorQueryCache } from './calculator-query-cache';

/** HTTP-слой admin API с in-memory кэшем GET. */
@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private readonly http = inject(HttpClient);
  private readonly cache = inject(AdminQueryCache);
  private readonly calculatorCache = inject(CalculatorQueryCache);
  private readonly baseUrl = `${environment.apiBaseUrl}/admin`;

  getImports(options: {
    status?: ImportStatus | '';
    page?: number;
    perPage?: number;
  } = {}): Observable<PaginatedDto<TariffImportListItemDto>> {
    const status = options.status ?? '';
    const page = options.page ?? 1;
    const perPage = options.perPage ?? 15;
    const key = this.importsListKey(status, page, perPage);

    return this.cache.getOrLoad(key, () => {
      let params = new HttpParams()
        .set('page', String(page))
        .set('per_page', String(perPage));
      if (status) {
        params = params.set('status', status);
      }

      return this.http.get<PaginatedDto<TariffImportListItemDto>>(`${this.baseUrl}/imports`, {
        params,
      });
    });
  }

  getImport(id: number): Observable<TariffImportDetailDto> {
    const key = this.importDetailKey(id);

    return this.cache.getOrLoad(key, () =>
      this.http.get<TariffImportDetailDto>(`${this.baseUrl}/imports/${id}`),
    );
  }

  uploadImport(file: File): Observable<TariffImportDetailDto> {
    const body = new FormData();
    body.append('file', file, file.name);

    return this.http.post<TariffImportDetailDto>(`${this.baseUrl}/imports`, body).pipe(
      tap((detail) => {
        this.cache.invalidate(['imports', 'tariffs']);
        this.cache.set(this.importDetailKey(detail.id), detail);
      }),
    );
  }

  activateImport(id: number): Observable<TariffImportDetailDto> {
    return this.http.post<TariffImportDetailDto>(`${this.baseUrl}/imports/${id}/activate`, {}).pipe(
      tap((detail) => {
        this.cache.invalidate(['imports', 'tariffs']);
        this.cache.set(this.importDetailKey(detail.id), detail);
        this.calculatorCache.clear();
      }),
    );
  }

  rollbackImport(id: number): Observable<TariffImportDetailDto> {
    return this.http.post<TariffImportDetailDto>(`${this.baseUrl}/imports/${id}/rollback`, {}).pipe(
      tap((detail) => {
        this.cache.invalidate(['imports', 'tariffs']);
        this.cache.set(this.importDetailKey(detail.id), detail);
        this.calculatorCache.clear();
      }),
    );
  }

  getTariffs(options: {
    platform?: PlatformCode | '';
    revisionId?: number | null;
  } = {}): Observable<AdminTariffDto[]> {
    const platform = options.platform ?? '';
    const revisionId =
      options.revisionId === undefined || options.revisionId === null
        ? ''
        : String(options.revisionId);
    const key = this.tariffsKey(platform, revisionId);

    return this.cache.getOrLoad(key, () => {
      let params = new HttpParams();
      if (platform) {
        params = params.set('platform', platform);
      }
      if (revisionId !== '') {
        params = params.set('revision_id', revisionId);
      }

      return this.http.get<AdminTariffDto[]>(`${this.baseUrl}/tariffs`, { params });
    });
  }

  getSettings(): Observable<AdminSettingsDto> {
    return this.cache.getOrLoad('settings', () =>
      this.http.get<AdminSettingsDto>(`${this.baseUrl}/settings`),
    );
  }

  updateExchangeRate(rubToCnyRate: string): Observable<AdminSettingsDto> {
    return this.http
      .put<AdminSettingsDto>(`${this.baseUrl}/settings/exchange-rate`, {
        rub_to_cny_rate: rubToCnyRate,
      })
      .pipe(
        tap((settings) => {
          this.cache.set('settings', settings);
          this.calculatorCache.clear();
        }),
      );
  }

  /**
   * Прогрев основных admin-эндпоинтов при входе в shell / после login.
   * Ошибки отдельных запросов не валят весь прогрев.
   */
  prefetchWarmup(): Observable<void> {
    return forkJoin([
      this.getImports({ page: 1, perPage: 15 }).pipe(catchError(() => of(null))),
      this.getImports({ perPage: 100 }).pipe(catchError(() => of(null))),
      this.getTariffs().pipe(catchError(() => of(null))),
      this.getSettings().pipe(catchError(() => of(null))),
    ]).pipe(map(() => undefined));
  }

  private importsListKey(status: string, page: number, perPage: number): string {
    return `imports?status=${status}&page=${page}&per_page=${perPage}`;
  }

  private importDetailKey(id: number): string {
    return `imports:${id}`;
  }

  private tariffsKey(platform: string, revisionId: string): string {
    return `tariffs?platform=${platform}&revision_id=${revisionId}`;
  }
}
