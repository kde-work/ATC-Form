# XLSX fixtures

Файлы собраны по `docs/xlsx-import-format.md` (оригинала заказчика в репозитории нет).

| Файл | Назначение |
|------|------------|
| `valid-tariffs.xlsx` | 15 Ozon + 2 Yandex |
| `invalid-duplicate-code.xlsx` | дубль Ozon code |
| `invalid-broken-rate.xlsx` | неразбираемая Ozon rate |

Пересборка: `php85 tests/Fixtures/generate_xlsx_fixtures.php`
