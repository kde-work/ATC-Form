import { PlatformDto } from '../models/api.models';

/**
 * Фиксированный список платформ калькулятора.
 * Совпадает с App\Enums\Platform; не ходим на GET /platforms.
 */
export const CALCULATOR_PLATFORMS: readonly PlatformDto[] = [
  { code: 'ozon', name: 'Ozon' },
  { code: 'yandex_market', name: 'Yandex Market' },
] as const;
