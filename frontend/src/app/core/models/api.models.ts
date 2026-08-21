/** Коды платформ публичного API. */
export type PlatformCode = 'ozon' | 'yandex_market';

/** Валюта стоимости заказа. */
export type OrderCostCurrency = 'CNY' | 'RUB';

export interface PlatformDto {
  code: PlatformCode;
  name: string;
}

export interface DeliveryChannelDto {
  code: string;
  name: string;
  platform: PlatformCode;
  currency: string;
}

export interface PublicSettingsDto {
  rub_to_cny_rate: string;
  updated_at: string;
}

export interface NamedCodeDto {
  code: string;
  name: string;
}

/** Успешный ответ POST /calculations (eligible true/false). */
export interface CalculationResultDto {
  eligible: boolean;
  platform: NamedCodeDto;
  channel: NamedCodeDto;
  currency: string;
  physical_weight_grams: string;
  fixed_fee: string | null;
  final_cost: string | null;
  errors: string[];
  warnings: string[];
  // Ozon
  volumetric_weight_grams?: string | null;
  chargeable_weight_grams?: string | null;
  rate_per_gram?: string | null;
  order_cost?: string | null;
  order_cost_currency?: OrderCostCurrency | null;
  order_cost_cny?: string | null;
  // Yandex
  billed_weight_grams?: string | null;
  rate_per_kg?: string | null;
  exchange_rate?: string | null;
  final_cost_cny?: string | null;
}

export interface CalculationRequestDto {
  platform: PlatformCode;
  delivery_channel_code: string;
  physical_weight_grams: string;
  length_cm?: string;
  width_cm?: string;
  height_cm?: string;
  order_cost?: string;
  order_cost_currency?: OrderCostCurrency;
}

/** Единый JSON ошибок API. */
export interface ApiErrorBody {
  message: string;
  code: string;
  errors: Record<string, string[]>;
}

export class ApiClientError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fieldErrors: Record<string, string[]>;

  constructor(status: number, body: ApiErrorBody) {
    super(body.message || 'Request failed.');
    this.name = 'ApiClientError';
    this.status = status;
    this.code = body.code || 'http_error';
    this.fieldErrors = body.errors ?? {};
  }

  /** Плоский список сообщений для UI. */
  flatMessages(): string[] {
    const fromFields = Object.values(this.fieldErrors).flat();
    if (fromFields.length > 0) {
      return fromFields;
    }

    return [this.message];
  }
}
