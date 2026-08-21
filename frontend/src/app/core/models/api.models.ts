/** Коды платформ публичного API. */
export type PlatformCode = 'ozon' | 'yandex_market';

/** Валюта стоимости заказа. */
export type OrderCostCurrency = 'CNY' | 'RUB';

/** Статусы импорта тарифов. */
export type ImportStatus =
  | 'uploaded'
  | 'processing'
  | 'validation_failed'
  | 'validated'
  | 'activated'
  | 'failed';

/** Статусы ревизии тарифов. */
export type RevisionStatus = 'draft' | 'active' | 'archived' | 'invalid';

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

/** Ответ GET /calculator/bootstrap: все справочники формы. */
export interface CalculatorBootstrapDto {
  platforms: PlatformDto[];
  delivery_channels: DeliveryChannelDto[];
  settings: PublicSettingsDto;
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

export interface AdminUserDto {
  id: number;
  name: string;
  email: string;
}

export interface AdminLoginResponseDto {
  token: string;
  token_type: string;
  user: AdminUserDto;
}

export interface AdminSettingsDto {
  rub_to_cny_rate: string;
  updated_at: string;
  updated_by: AdminUserDto | null;
}

export interface ImportActionsDto {
  can_activate: boolean;
  can_rollback: boolean;
}

export interface ImportRevisionSummaryDto {
  id: number;
  version_number: number;
  status: RevisionStatus;
  is_active: boolean;
}

export interface TariffImportListItemDto {
  id: number;
  uploaded_at: string | null;
  original_filename: string;
  file_size_bytes: number;
  status: ImportStatus;
  uploaded_by: AdminUserDto | null;
  total_parsed: number | null;
  errors_count: number;
  revision: ImportRevisionSummaryDto | null;
  actions: ImportActionsDto;
}

export interface ImportSummaryDto {
  total: number;
  added: number;
  changed: number;
  removed: number;
  added_codes: string[];
  changed_codes: string[];
  removed_codes: string[];
}

export interface AdminTariffDto {
  id: number;
  revision_id: number;
  platform: PlatformCode;
  code: string;
  name: string;
  active: boolean;
  currency: string;
  chargeable_weight_type: string | null;
  fixed_fee: string | null;
  per_gram_fee: string | null;
  per_kg_fee: string | null;
  billing_increment_grams: number | null;
  volumetric_divisor: string | null;
  min_weight_grams: string | null;
  max_weight_grams: string | null;
  max_length_cm: string | null;
  max_sum_dimensions_cm: string | null;
  min_order_cost_rub: string | null;
  max_order_cost_rub: string | null;
  min_order_cost_cny: string | null;
  max_order_cost_cny: string | null;
  import_source_row: number | null;
}

export interface TariffImportErrorDto {
  id: number;
  sheet_name: string | null;
  row_number: number | null;
  field: string | null;
  error_code: string | null;
  message: string;
  context: Record<string, unknown> | null;
}

export interface ImportRevisionDetailDto {
  id: number;
  version_number: number;
  status: RevisionStatus;
  is_active: boolean;
  activated_at: string | null;
  archived_at: string | null;
  activated_by: AdminUserDto | null;
  metadata: Record<string, unknown> | null;
}

export interface TariffImportDetailDto {
  id: number;
  uploaded_at: string | null;
  started_at: string | null;
  finished_at: string | null;
  original_filename: string;
  file_hash: string;
  file_size_bytes: number;
  status: ImportStatus;
  uploaded_by: AdminUserDto | null;
  summary: ImportSummaryDto | null;
  diff: ImportSummaryDto | null;
  parsed: {
    total: number;
    by_platform: Record<string, number>;
    tariffs: AdminTariffDto[];
  };
  errors: TariffImportErrorDto[];
  revision: ImportRevisionDetailDto | null;
  actions: ImportActionsDto;
}

/** Laravel paginator для списка импортов (withoutWrapping). */
export interface PaginatedDto<T> {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
  };
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
