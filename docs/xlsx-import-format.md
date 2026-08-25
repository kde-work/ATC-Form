# Формат импорта XLSX

Правила для парсера и для тестовых fixture. Исходный файл заказчика в репозитории может отсутствовать: fixture собирать по этому документу и списку каналов ниже.

PhpSpreadsheet читает **уже вычисленные значения ячеек** (`getCalculatedValue` не использовать как источник бизнес-логики; брать `getValue` / formatted string и нормализовать самим). Формулы и VBA не исполнять.

## Листы, которые нужно найти

Книга может содержать лишние листы. Парсер ищет по нормализованному имени.

Нормализация имени листа:

1. trim;
2. схлопнуть повторные пробелы в один;
3. lowercase;
4. для сопоставления дополнительно убрать пробелы вокруг `+` и сравнить без учёта ё/е при необходимости.

| Назначение | Типичные имена | Как узнать |
|------------|----------------|------------|
| Ozon | `тарифы Ozon без формул + лимиты`, `тарифы без формул + лимиты`, `ozon tariffs`, `ozon` | имя содержит `ozon`, иначе лист с `лимит` / `без формул` (не `формулами`) |
| Yandex | лист `тарифы с формулами EXCEL` или любой лист, где есть секция Yandex Market | имя содержит `yandex`; иначе лист с секцией `yandex market` / `яндекс`, предпочтительно где есть оба канала Super Express и Express (описание калькулятора с упоминанием YM не выбирается) |

Если Ozon-лист не найден: ошибка импорта без row (`field`: `workbook`, сообщение что лист Ozon не найден).  
Если Yandex-секция не найдена: то же для Yandex. Оба листа обязательны для валидного импорта.

Пробелы и смесь ru/en в названии не должны ронять импорт, если ключ платформы узнаваем.

## Общие правила ячеек

- Пустые строки пропускать.
- Строка-заголовок: не тариф. Заголовок узнаём по ключевым словам (`name`, `канал`, `weight`, `вес`, `rate`, `тариф`, `limit`).
- Числа: допускать запятую и точку как десятичный разделитель; пробелы-разделители тысяч убирать; символ валюты (`¥`, `₽`, `CNY`, `RUB`) отбрасывать до числа.
- Диапазон веса `1 - 1500`, `1–1500`, `1..1500`: левая граница -> `min_weight_grams`, правая -> `max_weight_grams`.
- Отдельные колонки min g / max g: каждое число в свою границу.
- Диапазон стоимости с пробелом в числе: `135. 01 - 635`, `7001 - 250 000`.
- Нечисловой мусор в тарифной ячейке: ошибка этой строки, не всего файла.

## Код канала (`code`)

Стабильный machine-id. Считается из отображаемого имени, не из номера строки.

Алгоритм:

1. взять английское имя канала (для Yandex: Super Express / Express, без китайского комментария);
2. lowercase;
3. всё, что не `[a-z0-9]+`, заменить на дефис;
4. схлопнуть дефисы, обрезать края.

Примеры:

| name | code |
|------|------|
| ATC Express Extra Small | `atc-express-extra-small` |
| ATC Standard Big | `atc-standard-big` |
| ATC Economy Premium Big | `atc-economy-premium-big` |
| Super Express | `super-express` |
| Express | `express` |

Дубль `(platform, code)` в одной книге: ошибка валидации, activate нельзя.

## Ozon

Ожидаемые каналы (ставки **не** зашивать в PricingService, только в fixture/XLSX):

- ATC Express Extra Small
- ATC Standard Extra Small
- ATC Economy Extra Small
- ATC Standard Budget
- ATC Economy Budget
- ATC Express Small
- ATC Standard Small
- ATC Economy Small
- ATC Standard Big
- ATC Economy Big
- ATC Express Premium Small
- ATC Standard Premium Small
- ATC Economy Premium Small
- ATC Standard Premium Big
- ATC Economy Premium Big

Валюта всегда CNY. Если в файле явно другая: ошибка строки.

### Как читать строку Ozon

Минимальный набор после нормализации:

- `name`
- `fixed_fee` >= 0
- `per_gram_fee` > 0
- `min_weight_grams`, `max_weight_grams` (min <= max)
- `max_length_cm` > 0 если задан
- `max_sum_dimensions_cm` > 0 если задан
- лимиты стоимости заказа: nullable пары CNY и/или RUB

Колонки в исходнике заказчика (лист `тарифы Ozon без формул + лимиты` или `тарифы без формул + лимиты`):

| Заголовок | Поле |
|-----------|------|
| Delivery Method | name |
| Rates (PUDO / Courier) | fixed_fee + per_gram_fee |
| Measurements, max cm | max_sum_dimensions_cm, max_length_cm |
| Shipment weight limits / min g | min_weight_grams |
| Shipment weight limits / max g | max_weight_grams |
| Shipment cost limit / min-max \| RUB | min/max_order_cost_rub |
| Shipment cost limit / min-max \| CNY | min/max_order_cost_cny |

Допустим и компактный layout: `Channel`, `Weight g` (диапазон `1 - 1500`), `Rate`, `Limits`, `Order CNY`, `Order RUB`.

Искать по нормализованному заголовку (lowercase, без лишних пробелов): delivery method/channel/name, weight min/max или weight/вес, rate/тариф/fee, measurements/limits/ограничения, cost+RUB, cost+CNY.

### Ставка вида `¥ 3.37 + ¥ 0.0505/1 g`

Разбор regex-логикой, не Excel-формулой:

- первое число: `fixed_fee`;
- второе число: `per_gram_fee`;
- суффикс `/1 g` или `/g` подтверждает per gram; иное: ошибка `cannot parse per-gram rate`.

Примеры:

```text
¥ 3.37 + ¥ 0.0505/1 g    -> fixed 3.37, per_gram 0.0505
3.37 + 0.0505/g          -> то же
¥40.44 + ¥0.0281/1 g     -> 40.44 и 0.0281
```

### Лимиты сторон

Строки вроде:

```text
Sum of sides ≤ 90 cm, length ≤ 60 cm
sum of sides <= 90cm, length <= 60cm
сумма сторон не более 90 см, длина не более 60 см
```

Извлечь:

- `max_sum_dimensions_cm` из sum of sides / сумма сторон;
- `max_length_cm` из length / длина / max side.

Символы `≤`, `<=`, `не более` равнозначны.

### Тип платного веса

По имени канала (после построения `code`):

- если `code` содержит `big` (включая `premium-big`):  
  `chargeable_weight_type = max_physical_or_volumetric`,  
  `volumetric_divisor` обязателен (в данных обычно `12000`; если в файле нет ячейки divisor, подставить `12000` только как значение записи тарифа на этапе импорта, не в PricingService);
- иначе: `physical`, `volumetric_divisor = null`.

Не путать Extra Small и Small с Big.

### Пример логической строки Ozon (для fixture)

Лист `тарифы Ozon без формул + лимиты`:

| Delivery Method | Rates | Measurements | min g | max g | Order RUB | Order CNY |
|-----------------|-------|--------------|-------|-------|-----------|-----------|
| ATC Standard Extra Small | ¥ 3.37 + ¥ 0.0393/1 g | Sum of sides ≤ 90 cm, length ≤ 60 cm | 1 | 500 | 1 - 1500 | 0.01 - 135 |
| ATC Standard Big | ¥ 40.44 + ¥ 0.0281/1 g | Sum of sides ≤ 310 cm, length ≤ 150 cm | 2001 | 30000 | 1501 - 7000 | 135.01 - 635 |

После парсинга Extra Small:

- platform `ozon`, code `atc-standard-extra-small`
- min_weight 1, max_weight 500
- fixed 3.37, per_gram 0.0393, currency CNY
- type `physical`
- max_sum 90, max_length 60
- min_order_cost_rub 1, max_order_cost_rub 1500
- min_order_cost_cny 0.01, max_order_cost_cny 135

Big:

- type `max_physical_or_volumetric`, divisor 12000
- max_sum 310, max_length 150

Числа в fixture могут отличаться от боевых тарифов заказчика. Для тестов расчёта важна согласованность fixture и unit-кейсов.

Валидный импорт должен содержать **все** 15 имён из списка выше. Отсутствие канала: ошибка workbook-уровня с именем канала. Лишний неизвестный канал Ozon: допустим, если проходит валидацию полей (чтобы не ломать будущие имена); дубликаты code запрещены.

## Yandex Market

Секция на листе (часто рядом с формулами, которые **игнорировать**). Искать заголовок секции и две строки каналов.

Два layout:

1. Строка канала: имя Super Express / Express в первой колонке, ставка `193 + 948 RUB/kg`.
2. Таблица исходника: шапка с именами каналов, строки `Ставка за кг` (`948 ₽/кг`) и `Фиксированная ставка` (`193 ₽/шт`).

Каналы:

| name | code | fixed_fee RUB | per_kg_fee | increment g |
|------|------|---------------|------------|-------------|
| Super Express | super-express | 193 | 948 | 100 |
| Express | express | 198 | 787 | 100 |

Китайские подписи (`空运卡航`, `卡航陆运`) в `name` не обязательны; можно сложить в notes/`raw_data_json`.

Валюта RUB. `chargeable_weight_type` для Yandex не используется в расчёте (округление через `billing_increment_grams`).

Габариты и order cost limits: если ячеек нет, оставить null. **Не выдумывать.**

Формат ставки в файле может быть:

```text
193 + 948 RUB/kg
198 RUB + 787 /kg
```

Первое число: fixed, второе: per kg. Если per_kg не > 0: ошибка.

Оба канала обязательны для валидного импорта.

## Что считать ошибкой чтения файла

Статус import `failed` (не validation_failed):

- не xlsx / zip-контейнер повреждён;
- PhpSpreadsheet не открыл книгу;
- расширение не `.xlsx`.

Макрос не запускать; наличие vba не обязано валить импорт, если листы читаются.

## Что считать validation_failed

Любая бизнес/структурная ошибка после того, как книга открылась. Revision `invalid`, Activate скрыт.

Правила (сообщения на английском, с sheet и row если есть):

- нет обязательных листов/секций;
- пустое имя канала;
- дубль code в рамках platform;
- Ozon: не разобрали per-gram rate; per_gram_fee <= 0; нет Big-divisor для Big;
- Yandex: нет Super Express / Express; нет per_kg; per_kg <= 0;
- fixed_fee < 0;
- min_weight > max_weight;
- min_order_cost > max_order_cost в той же валюте;
- max_length или max_sum заданы и <= 0;
- валюта не совпала с платформой;
- после парсинга 0 каналов (пустая ревизия).

Несколько ошибок одной строки: несколько записей `tariff_import_errors`.

## Примеры сообщений

```text
Sheet 'тарифы Ozon без формул + лимиты', row 12: cannot parse per-gram rate.
Sheet 'тарифы Ozon без формул + лимиты', row 8: min weight cannot exceed max weight.
Sheet 'тарифы Ozon без формул + лимиты', row 4: duplicate channel code 'atc-standard-big'.
Yandex Market section: Super Express tariff is missing a fixed fee.
Workbook: Ozon sheet not found.
Workbook: Ozon channel 'ATC Economy Premium Big' is missing.
```

Поля в БД:

- sheet_name: исходное имя листа;
- row_number: 1-based как в Excel;
- field: `per_gram_fee`, `min_weight_grams`, `code`, `workbook`, ...;
- message: как выше.

## Fixture для тестов

Каталог: `tests/Fixtures/xlsx/`.

| Файл | Ожидание |
|------|----------|
| `valid-tariffs.xlsx` | 15 Ozon + 2 Yandex, layout исходника заказчика |
| `customer-ozon-ym.xlsx` | копия исходного файла заказчика |
| `invalid-duplicate-code.xlsx` | `validation_failed`, ошибка с sheet и row |
| `invalid-broken-rate.xlsx` | строка Ozon с неразбираемой ставкой |

Собирать fixture кодом (PhpSpreadsheet) в seeder/тесте допустимо, если бинарник неудобно хранить. Правила ячеек те же.

## Граница с расчётом

Парсер не считает volumetric weight и final cost. Он только заполняет поля `delivery_channels`. `PricingService` читает уже сохранённый тариф активной ревизии.
