import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
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

/** HTTP-слой admin API: imports, tariffs, settings. */
@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiBaseUrl}/admin`;

  getImports(options: {
    status?: ImportStatus | '';
    page?: number;
    perPage?: number;
  } = {}): Observable<PaginatedDto<TariffImportListItemDto>> {
    let params = new HttpParams();
    if (options.status) {
      params = params.set('status', options.status);
    }
    if (options.page !== undefined) {
      params = params.set('page', String(options.page));
    }
    if (options.perPage !== undefined) {
      params = params.set('per_page', String(options.perPage));
    }

    return this.http.get<PaginatedDto<TariffImportListItemDto>>(`${this.baseUrl}/imports`, {
      params,
    });
  }

  getImport(id: number): Observable<TariffImportDetailDto> {
    return this.http.get<TariffImportDetailDto>(`${this.baseUrl}/imports/${id}`);
  }

  uploadImport(file: File): Observable<TariffImportDetailDto> {
    const body = new FormData();
    body.append('file', file, file.name);

    return this.http.post<TariffImportDetailDto>(`${this.baseUrl}/imports`, body);
  }

  activateImport(id: number): Observable<TariffImportDetailDto> {
    return this.http.post<TariffImportDetailDto>(`${this.baseUrl}/imports/${id}/activate`, {});
  }

  rollbackImport(id: number): Observable<TariffImportDetailDto> {
    return this.http.post<TariffImportDetailDto>(`${this.baseUrl}/imports/${id}/rollback`, {});
  }

  getTariffs(options: {
    platform?: PlatformCode | '';
    revisionId?: number | null;
  } = {}): Observable<AdminTariffDto[]> {
    let params = new HttpParams();
    if (options.platform) {
      params = params.set('platform', options.platform);
    }
    if (options.revisionId !== undefined && options.revisionId !== null) {
      params = params.set('revision_id', String(options.revisionId));
    }

    return this.http.get<AdminTariffDto[]>(`${this.baseUrl}/tariffs`, { params });
  }

  getSettings(): Observable<AdminSettingsDto> {
    return this.http.get<AdminSettingsDto>(`${this.baseUrl}/settings`);
  }

  updateExchangeRate(rubToCnyRate: string): Observable<AdminSettingsDto> {
    return this.http.put<AdminSettingsDto>(`${this.baseUrl}/settings/exchange-rate`, {
      rub_to_cny_rate: rubToCnyRate,
    });
  }
}
