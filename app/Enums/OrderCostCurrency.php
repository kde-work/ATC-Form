<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Валюта стоимости заказа во входных данных расчёта.
 */
enum OrderCostCurrency: string
{
    case Cny = 'CNY';
    case Rub = 'RUB';
}
