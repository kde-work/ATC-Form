<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ExchangeRate;

use App\Services\ExchangeRate\CbrDailyRateClient;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Парсинг XML ЦБ РФ в курс RUB → CNY (Nominal / Value).
 */
final class CbrDailyRateClientTest extends TestCase
{
    public function test_parse_cny_nominal_over_value(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs Date="22.08.2026" name="Foreign Currency Market">
  <Valute ID="R01235">
    <NumCode>840</NumCode>
    <CharCode>USD</CharCode>
    <Nominal>1</Nominal>
    <Name>Доллар США</Name>
    <Value>82,9211</Value>
  </Valute>
  <Valute ID="R01375">
    <NumCode>156</NumCode>
    <CharCode>CNY</CharCode>
    <Nominal>1</Nominal>
    <Name>Юань</Name>
    <Value>12,3343</Value>
  </Valute>
</ValCurs>
XML;

        $rate = (new CbrDailyRateClient())->parseRubToCnyFromXml($xml);

        // 1 / 12.3343 = 0.08107472 (bcmath scale 8)
        $this->assertSame('0.08107472', $rate);
    }

    public function test_parse_respects_nominal_greater_than_one(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<ValCurs>
  <Valute>
    <CharCode>CNY</CharCode>
    <Nominal>10</Nominal>
    <Value>123,3430</Value>
  </Valute>
</ValCurs>
XML;

        $rate = (new CbrDailyRateClient())->parseRubToCnyFromXml($xml);

        $this->assertSame('0.08107472', $rate);
    }

    #[DataProvider('invalidXmlProvider')]
    public function test_parse_rejects_invalid_payload(string $xml): void
    {
        $this->expectException(RuntimeException::class);

        (new CbrDailyRateClient())->parseRubToCnyFromXml($xml);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidXmlProvider(): array
    {
        return [
            'not xml' => ['not-xml'],
            'missing cny' => ['<?xml version="1.0"?><ValCurs><Valute><CharCode>USD</CharCode><Nominal>1</Nominal><Value>1</Value></Valute></ValCurs>'],
            'zero value' => ['<?xml version="1.0"?><ValCurs><Valute><CharCode>CNY</CharCode><Nominal>1</Nominal><Value>0</Value></Valute></ValCurs>'],
        ];
    }
}
