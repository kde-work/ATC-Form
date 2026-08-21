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

/** HTTP-слой публичного калькулятора. Расчёт только на backend. */
@Injectable({ providedIn: 'root' })
export class CalculatorApiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiBaseUrl;

  /** Презагрузка platforms, всех каналов и публичных настроек. */
  getBootstrap(): Observable<CalculatorBootstrapDto> {
    return this.http.get<CalculatorBootstrapDto>(`${this.baseUrl}/calculator/bootstrap`);
  }

  getPlatforms(): Observable<PlatformDto[]> {
    return this.http.get<PlatformDto[]>(`${this.baseUrl}/platforms`);
  }

  getDeliveryChannels(platform: PlatformCode): Observable<DeliveryChannelDto[]> {
    return this.http.get<DeliveryChannelDto[]>(`${this.baseUrl}/delivery-channels`, {
      params: { platform },
    });
  }

  getPublicSettings(): Observable<PublicSettingsDto> {
    return this.http.get<PublicSettingsDto>(`${this.baseUrl}/settings/public`);
  }

  calculate(payload: CalculationRequestDto): Observable<CalculationResultDto> {
    return this.http.post<CalculationResultDto>(`${this.baseUrl}/calculations`, payload);
  }
}
