<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Учётные данные администратора (только seeder, не саморегистрация)
    |--------------------------------------------------------------------------
    */

    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Лимит размера XLSX импорта тарифов (байты)
    |--------------------------------------------------------------------------
    */

    'tariff_import_max_bytes' => (int) env('TARIFF_IMPORT_MAX_BYTES', 5_242_880),

    /*
    |--------------------------------------------------------------------------
    | Автообновление курса RUB → CNY (wp-cron style, без системного cron)
    |--------------------------------------------------------------------------
    |
    | Источник: ежедневный XML ЦБ РФ. Триггер: любой API-запрос, если прошло
    | interval_seconds с последней успешной попытки. Работа выполняется после
    | ответа клиенту (afterResponse), очередь не нужна.
    |
    */

    'exchange_rate' => [
        'cbr_url' => env('EXCHANGE_RATE_CBR_URL', 'https://www.cbr.ru/scripts/XML_daily.asp'),
        'interval_seconds' => (int) env('EXCHANGE_RATE_SYNC_INTERVAL', 86_400),
        'retry_after_seconds' => (int) env('EXCHANGE_RATE_RETRY_AFTER', 900),
        'http_timeout_seconds' => (int) env('EXCHANGE_RATE_HTTP_TIMEOUT', 5),
    ],

];
