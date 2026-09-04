import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  CalculationRequestDto,
  CalculationResultDto,
  CalculatorBootstrapDto,
  DeliveryChannelDto,
  PlatformCode,
  PlatformDto,
  PublicSettingsDto,
} from '../models/api.models';
import { CalculatorQueryCache } from './calculator-query-cache';

/** HTTP-слой публичного калькулятора. Расчёт только на backend. */
@Injectable({ providedIn: 'root' })
export class CalculatorApiService {
  private readonly http = inject(HttpClient);
  private readonly cache = inject(CalculatorQueryCache);
  private readonly baseUrl = environment.apiBaseUrl;

  /** Презагрузка каналов и публичных настроек (платформы вшиты во фронт). */
  getBootstrap(): Observable<CalculatorBootstrapDto> {
    return this.cache.getOrLoad('bootstrap', () =>
      this.http.get<CalculatorBootstrapDto>(`${this.baseUrl}/calculator/bootstrap`),
    );
  }

  /** @deprecated Платформы фиксированы в CALCULATOR_PLATFORMS; не вызывать с формы калькулятора. */
  getPlatforms(): Observable<PlatformDto[]> {
    return this.cache.getOrLoad('platforms', () =>
      this.http.get<PlatformDto[]>(`${this.baseUrl}/platforms`),
    );
  }

  getDeliveryChannels(platform: PlatformCode): Observable<DeliveryChannelDto[]> {
    return this.cache.getOrLoad(`delivery-channels:${platform}`, () =>
      this.http.get<DeliveryChannelDto[]>(`${this.baseUrl}/delivery-channels`, {
        params: { platform },
      }),
    );
  }

  getPublicSettings(): Observable<PublicSettingsDto> {
    return this.cache.getOrLoad('settings:public', () =>
      this.http.get<PublicSettingsDto>(`${this.baseUrl}/settings/public`),
    );
  }

  /** POST расчёта не кэшируется. */
  calculate(payload: CalculationRequestDto): Observable<CalculationResultDto> {
    return this.http.post<CalculationResultDto>(`${this.baseUrl}/calculations`, payload);
  }
}
