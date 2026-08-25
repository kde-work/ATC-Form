# XLSX fixtures

Файлы собраны по `docs/xlsx-import-format.md`. `customer-ozon-ym.xlsx` — копия исходника заказчика.

| Файл | Назначение |
|------|------------|
| `valid-tariffs.xlsx` | 15 Ozon + 2 Yandex, колонки исходника |
| `customer-ozon-ym.xlsx` | исходный файл заказчика |
| `invalid-duplicate-code.xlsx` | дубль Ozon code |
| `invalid-broken-rate.xlsx` | неразбираемая Ozon rate |

Пересборка generated-файлов: `php85 tests/Fixtures/generate_xlsx_fixtures.php`
