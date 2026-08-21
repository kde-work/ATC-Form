<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Платформа доставки в калькуляторе и тарифах.
 */
enum Platform: string
{
    case Ozon = 'ozon';
    case YandexMarket = 'yandex_market';

    /**
     * Человекочитаемое имя для API и UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ozon => 'Ozon',
            self::YandexMarket => 'Yandex Market',
        };
    }

    /**
     * Для Ozon габариты и стоимость заказа обязательны на входе расчёта.
     */
    public function requiresDimensionsAndOrderCost(): bool
    {
        return $this === self::Ozon;
    }
}
