<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate;

use App\Support\Decimal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Загрузка официального курса CNY с сайта ЦБ РФ (XML_daily).
 *
 * ЦБ отдаёт Value как RUB за Nominal единиц валюты. Для калькулятора нужен
 * rub_to_cny: CNY за 1 RUB = Nominal / Value.
 */
final class CbrDailyRateClient
{
    /**
     * @return numeric-string курс RUB → CNY
     *
     * @throws RuntimeException
     */
    public function fetchRubToCnyRate(): string
    {
        /** @var array{cbr_url: string, http_timeout_seconds: int} $config */
        $config = config('atc.exchange_rate');

        try {
            $response = Http::timeout($config['http_timeout_seconds'])
                ->accept('application/xml, text/xml, */*')
                ->get($config['cbr_url']);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Не удалось скачать курсы ЦБ РФ: ' . $e->getMessage(), 0, $e);
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException(
                'ЦБ РФ вернул ошибку HTTP ' . $response->status() . '.',
                0,
                $e,
            );
        }

        return $this->parseRubToCnyFromXml($response->body());
    }

    /**
     * @return numeric-string
     *
     * @throws RuntimeException
     */
    public function parseRubToCnyFromXml(string $xml): string
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = simplexml_load_string($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document === false) {
            throw new RuntimeException('Ответ ЦБ РФ не является корректным XML.');
        }

        foreach ($document->Valute as $valute) {
            $charCode = trim((string) $valute->CharCode);
            if ($charCode !== 'CNY') {
                continue;
            }

            $nominalRaw = str_replace(',', '.', trim((string) $valute->Nominal));
            $valueRaw = str_replace(',', '.', trim((string) $valute->Value));

            if ($nominalRaw === '' || $valueRaw === '' || ! is_numeric($nominalRaw) || ! is_numeric($valueRaw)) {
                throw new RuntimeException('В XML ЦБ РФ у CNY некорректные Nominal/Value.');
            }

            try {
                $nominal = Decimal::normalize($nominalRaw, 8);
                $value = Decimal::normalize($valueRaw, 8);
            } catch (\InvalidArgumentException $e) {
                throw new RuntimeException('В XML ЦБ РФ у CNY некорректные Nominal/Value.', 0, $e);
            }

            if (Decimal::compare($nominal, '0', 8) <= 0 || Decimal::compare($value, '0', 8) <= 0) {
                throw new RuntimeException('В XML ЦБ РФ у CNY Nominal/Value должны быть больше нуля.');
            }

            return Decimal::normalize(Decimal::div($nominal, $value, 8), 8);
        }

        throw new RuntimeException('В XML ЦБ РФ не найден курс CNY.');
    }
}
