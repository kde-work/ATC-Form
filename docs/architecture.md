# Архитектура ATC Express Calculator

Документ для следующей сессии разработки. Продуктовая постановка: `.tmp/task/task.md`. Формат импорта Excel: `docs/xlsx-import-format.md`.

## Назначение

Web-приложение считает стоимость доставки ATC Express по активным тарифам двух платформ: Ozon и Yandex Market. Гость пользуется публичным калькулятором. Администратор обновляет тарифы загрузкой XLSX, смотрит preview, активирует ревизию или делает rollback, задаёт курс RUB к CNY.

Расчёт и ограничения живут только на backend. Frontend показывает форму и результат, но не является источником истины по формулам.

## Состав репозитория

Монорепозиторий:

- корень: Laravel API (nginx уже смотрит в `public/`);
- `frontend/`: Angular SPA (появится на этапе 9);
- `docker/`: PHP-FPM, nginx;
- `docs/`: соглашения.

Локальный стек уже задан Docker Compose: PHP 8.5, nginx, MySQL 8.4, Mailpit, Node (profile `frontend`). Redis в проекте нет: сессии и кэш на `file`, очередь `sync`.

## UI и типографика

Язык интерфейса: английский. Кириллица в данных допустима: имена каналов и листов XLSX, тексты ошибок импорта, имена файлов.

Шрифт приложения: Inter. Обязательные подмножества глифов: latin, latin-ext, cyrillic, cyrillic-ext. Подключать self-host (`@fontsource-variable/inter` или локальные woff2). Не использовать Google Fonts CSS2 как единственный источник: ответ режется по User-Agent, кириллица может не приехать. Instrument Sans и Figtree из скелета Laravel не использовать: кириллицы нет.

## Модули

Четыре зоны ответственности. Не смешивать.

### 1. Публичный калькулятор

Гостевые read-only операции:

- список платформ;
- список активных каналов выбранной платформы из активной ревизии;
- публичный курс RUB к CNY;
- расчёт стоимости.

Нет авторизации. Guest не меняет курс и тарифы.

### 2. Admin API

Токен Sanctum (Bearer). Без саморегистрации. Один admin из seeder (`ADMIN_EMAIL`, `ADMIN_PASSWORD`).

- вход / выход / текущий пользователь;
- история импортов, загрузка XLSX, детали, activate, rollback;
- просмотр тарифов (только чтение);
- чтение и смена курса.

Защита: middleware `auth:sanctum` на `/api/v1/admin/*` кроме `POST /api/v1/admin/login`. Политики Laravel точечно, где нужна проверка владельца или статуса (активация чужого импорта админу разрешена: в системе один admin).

### 3. Импорт тарифов

Цепочка, которую нельзя склеивать в один «Manager»:

1. сохранить файл в private storage;
2. разобрать книгу Excel в нормализованные записи (парсеры, см. `xlsx-import-format.md`);
3. проверить бизнес-правила импорта;
4. записать `tariff_import`, ошибки, draft-ревизию и каналы;
5. посчитать preview относительно текущей active-ревизии;
6. активировать или откатиться отдельной транзакцией.

MVP: шаги 2-5 синхронно в HTTP-запросе. Код вынести так, чтобы тот же Action/Job можно было повесить на очередь без смены доменной логики.

PhpSpreadsheet читает **значения ячеек**. Формулы Excel и VBA не исполнять.

### 4. Расчёт

Не зависит от Excel и не содержит зашитых ставок и списка каналов.

- вход: platform, channel code, вес, габариты, стоимость заказа, валюта стоимости;
- канал берётся из активной ревизии;
- `EligibilityValidationService`: все нарушения сразу, тексты на английском;
- `PricingService`: стоимость только если eligible, иначе `final_cost = null`.

Деньги и веса: decimal-строки (bcmath или аналог). Во float не считать и в JSON float не отдавать.

## Роли и доступ

| Роль | Кто | Что можно |
|------|-----|-----------|
| guest | неаутентифицированный клиент | только публичные `/api/v1/*` кроме `/admin` |
| admin | пользователь из seeder | публичное API плюс весь `/api/v1/admin/*` |

Саморегистрации нет. Смены пароля в MVP нет.

Angular:

- `/calculator` без токена;
- `/admin/**` только с токеном, иначе редирект на `/admin/login`.

## Как Angular ходит в API

- Базовый URL API: тот же origin через nginx либо явный `apiBaseUrl` на dev-сервере. Prefix: `/api/v1`.
- Публичные запросы без `Authorization`.
- Админские: заголовок `Authorization: Bearer {token}`.
- Content-Type: `application/json`, кроме upload импорта: `multipart/form-data`.
- Даты в ответах: ISO-8601.
- Денежные и весовые величины: строки.

Токен выдаёт `POST /api/v1/admin/login`. Cookie-SPA Sanctum не используем: фронт отдельное приложение, CORS настраивается явно.

## Публичные endpoints

| Метод | Путь | Поведение |
|-------|------|-----------|
| GET | `/api/v1/platforms` | Фиксированный список: `ozon`, `yandex_market` |
| GET | `/api/v1/delivery-channels?platform=` | `active=true` каналы **активной** ревизии. Нет active-ревизии: пустой массив `[]`, не ошибка |
| GET | `/api/v1/settings/public` | `{ "rub_to_cny_rate": "0.085000", "updated_at": "..." }` |
| POST | `/api/v1/calculations` | Расчёт. Канал не найден в active-ревизии: 422. Не eligible: 200, `eligible: false`, `final_cost: null` |

Если каналов ещё нет (свежий seed без импорта), калькулятор показывает пустой select. Это штатно.

## Admin endpoints

Все кроме login требуют Bearer.

| Метод | Путь |
|-------|------|
| POST | `/api/v1/admin/login` |
| POST | `/api/v1/admin/logout` |
| GET | `/api/v1/admin/me` |
| GET | `/api/v1/admin/tariffs` |
| GET | `/api/v1/admin/imports` |
| GET | `/api/v1/admin/imports/{id}` |
| POST | `/api/v1/admin/imports` |
| POST | `/api/v1/admin/imports/{id}/activate` |
| POST | `/api/v1/admin/imports/{id}/rollback` |
| GET | `/api/v1/admin/settings` |
| PUT | `/api/v1/admin/settings/exchange-rate` |

Пагинация: история импортов. Фильтр по `status`. Rate limit: login и upload.

## Формат ошибок API

Единый JSON для `/api/*`:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_error",
  "errors": {
    "physical_weight_grams": [
      "The physical weight grams field is required."
    ]
  }
}
```

Правила:

- `message`: краткий текст;
- `code`: машинный код (`validation_error`, `unauthenticated`, `forbidden`, `not_found`, `server_error`, `import_not_activatable` и т.д.);
- `errors`: объект поле -> список строк. Для ошибок без поля можно ключ `_`;
- HTTP: 401, 403, 404, 422, 429, 500;
- в production тело 500 без stack trace и без путей файлов.

Ошибки eligibility в успешном `POST /calculations` живут в `errors` ответа расчёта (массив строк), это не HTTP 422. 422 только на некорректный вход (нет платформы, отрицательный вес и т.д.).

## Модель данных

Таблицы минимального набора. Лишние сущности не вводить.

### users

Стандарт Laravel: `id`, `name`, `email`, `password`, timestamps. Роль в MVP не храним отдельной таблицей: любой существующий user считается admin. Саморегистрации нет, лишних пользователей seeder не создаёт.

### app_settings

Ключ-значение.

| Поле | Смысл |
|------|--------|
| id | PK |
| key | unique, например `rub_to_cny_rate` |
| value | строка; для курса decimal-строка |
| updated_by_user_id | nullable |
| timestamps | |

Стартовое значение курса после seed: `"0.085"` (документировать в README; это заглушка, не рыночный курс). Guest читает через public endpoint, меняет только admin.

### tariff_imports

Один загруженный файл и его обработка.

| Поле | Смысл |
|------|--------|
| id | PK |
| uploaded_by_user_id | кто загрузил |
| original_filename | имя с клиента |
| stored_path | путь в private disk |
| file_hash | sha256 содержимого |
| file_size_bytes | размер |
| status | см. статусы ниже |
| summary_json | счётчики, preview added/changed/removed |
| started_at, finished_at | |
| timestamps | |

Файл: диск `local` (не public), каталог вроде `tariff-imports/`. Максимум размера: env `TARIFF_IMPORT_MAX_BYTES`, default 5242880 (5 MB).

### tariff_import_errors

| Поле | Смысл |
|------|--------|
| id | PK |
| tariff_import_id | FK |
| sheet_name | nullable |
| row_number | nullable |
| field | nullable |
| error_code | nullable, машинный |
| message | человекочитаемое |
| context_json | nullable, без дампа всего файла |
| timestamps | |

### tariff_revisions

Версия набора тарифов. Импорт обычно создаёт одну revision. Rollback активирует старую revision, новый import для rollback не создаётся.

| Поле | Смысл |
|------|--------|
| id | PK |
| tariff_import_id | nullable (на будущее, если revision без файла) |
| version_number | unique integer, монотонно растёт |
| status | draft / active / archived / invalid |
| activated_by_user_id | nullable |
| activated_at, archived_at | nullable |
| metadata_json | nullable |
| timestamps | |

Инвариант: **в каждый момент ровно одна revision со статусом `active`, либо ни одной** (после seed до первой активации).

MySQL не умеет partial unique index PostgreSQL. Защита:

- generated column: `active_guard` = `1`, если `status = 'active'`, иначе `NULL`;
- UNIQUE на `active_guard` (несколько NULL допустимы, единица только одна);
- плюс транзакция activate/rollback: сначала снять active, потом назначить.

В PHPUnit (SQLite in-memory) generated column не создаётся тем же DDL: колонка `active_guard` обычная nullable unique, значение выставляет модель `TariffRevision` при save. На MySQL в Docker колонка STORED GENERATED.

Калькулятор и публичные каналы читают только `status = active`.

### delivery_channels

Снимок тарифа внутри revision. Редактирование руками запрещено: только импорт.

| Поле | Смысл |
|------|--------|
| id | PK |
| tariff_revision_id | FK |
| platform | `ozon` или `yandex_market` |
| code | машинный id, стабильный между импортами |
| name | отображаемое имя |
| active | канал включён внутри ревизии |
| currency | Ozon: CNY; Yandex: RUB |
| chargeable_weight_type | nullable: `physical` или `max_physical_or_volumetric` |
| fixed_fee | decimal(14, 6) |
| per_gram_fee | decimal(14, 8), nullable, Ozon |
| per_kg_fee | decimal(14, 6), nullable, Yandex |
| billing_increment_grams | integer nullable, Yandex обычно 100 |
| volumetric_divisor | decimal(14, 4) nullable, для Big |
| min_weight_grams, max_weight_grams | decimal(14, 3) nullable |
| max_length_cm, max_sum_dimensions_cm | decimal(14, 3) nullable |
| min_order_cost_rub, max_order_cost_rub | decimal(14, 4) nullable |
| min_order_cost_cny, max_order_cost_cny | decimal(14, 4) nullable |
| import_source_row | nullable |
| raw_data_json | nullable, исходные нормализованные ячейки для отладки |
| timestamps | |

Индексы:

- unique `(tariff_revision_id, platform, code)`;
- `(tariff_revision_id, platform, active)` под публичный список каналов.

Yandex: габаритные и стоимостные лимиты в исходном файле могут отсутствовать. В БД колонки nullable. **Не подставлять фиктивные лимиты.**

## Статусы импорта

`tariff_imports.status`:

| Статус | Когда |
|--------|--------|
| uploaded | файл принят, обработка ещё не стартовала (короткое окно) |
| processing | парсинг и валидация идут |
| validation_failed | файл прочитан, бизнес/структурные ошибки, revision `invalid` или без права activate |
| validated | ошибок нет, есть draft revision, можно Activate |
| activated | связанная revision стала active |
| failed | файл не прочитался, исключение, битый xlsx |

Переходы:

```
uploaded -> processing -> validated -> activated
                       -> validation_failed
                       -> failed
```

Activate из `validated`. Повторно активировать тот же import не нужно: после activate статус `activated`. Rollback идёт через другую revision (её import может остаться `activated` исторически; текущая active определяется по `tariff_revisions.status`, не по «последнему import»).

Нельзя активировать: пустую ревизию, ревизию с ошибками, import не в `validated` (для первой активации этой ревизии), draft с ошибками.

## Статусы ревизии

| Статус | Когда |
|--------|--------|
| draft | успешный импорт, ещё не включён в расчёт |
| active | единственный источник тарифов для калькулятора |
| archived | раньше была active, снята новой активацией или rollback |
| invalid | импорт с ошибками; activate запрещён |

## Flow: upload, preview, activate, rollback

```
Admin POST /admin/imports (xlsx)
  -> сохранить private storage
  -> tariff_import: uploaded, затем processing
  -> парсеры нормализуют строки
  -> валидация
     ошибки: import validation_failed, revision invalid, ошибки в tariff_import_errors
     ок: import validated, revision draft, каналы записаны
  -> summary_json: counts + diff к текущей active
  -> ответ с id, фронт открывает detail

Admin POST /admin/imports/{id}/activate
  -> только validated + draft без ошибок + непустые каналы
  -> транзакция:
       текущая active -> archived (archived_at)
       эта revision -> active (activated_at, activated_by)
       import -> activated
  -> калькулятор сразу видит новые каналы

Admin POST /admin/imports/{id}/rollback
  -> целевая revision должна быть archived и ранее валидной (не invalid)
  -> та же транзакция смены active
```

Preview (added / changed / removed) считается по паре `(platform, code)` относительно active. Нет active: все каналы added. Changed: те же code, но отличающиеся тарифные поля. Removed: были в active, нет в draft.

Rollback не удаляет ревизии и файлы.

## Расчёт: кратко

Точные формулы и поля результата: `task.md` разделы 3-5. Здесь инварианты:

- канал только из active revision, `active = true`;
- Ozon: габариты и order cost обязательны на входе API;
- Yandex: габариты и order cost необязательны и на этом этапе не влияют;
- guest курс не передаёт: всегда `app_settings.rub_to_cny_rate`;
- несколько нарушений eligibility: все в массиве `errors`;
- не eligible: HTTP 200, `eligible: false`, `final_cost: null`;
- UI-текст для этого случая: `This shipment is not eligible for the selected delivery channel.` плюс конкретные ошибки.

Ozon Big / Premium Big: `chargeable_weight_type = max_physical_or_volumetric`, divisor из строки тарифа (в данных обычно 12000), не магическая константа сервиса без запаса. Non-Big: `physical`, без округления веса, если increment не задан.

Yandex: `billed = ceil(weight / increment) * increment`, cost в RUB, CNY = RUB * курс.

## Слой кода (ориентир имён)

Не плодить DDD. Достаточно:

- тонкие контроллеры, Form Request, API Resource;
- enum: `Platform`, `ImportStatus`, `RevisionStatus`, `WeightCalculationType`;
- `PricingService`, `EligibilityValidationService`;
- парсеры книги/листов, `TariffImportValidator`, `TariffImportProcessor` (или Job), сервис активации ревизии.

Имена из ТЗ Perplexity не контракт. Границы ответственности: парсинг Excel ≠ валидация импорта ≠ активация ≠ расчёт.

## Принятые отклонения от ТЗ Perplexity

- БД: MySQL 8.4, не PostgreSQL (уже в Docker Compose).
- Redis нет: сессии и кэш `file`, очередь `sync` (импорт MVP и так синхронный).
- Laravel 13 в корне репозитория; если каркас недоступен: ближайший стабильный 12+ на PHP 8.5, версия в README.
- Auth: Sanctum personal access token (Bearer), не cookie SPA.
- Импорт MVP синхронный, с отдельным Action/Job под очередь.
- Нет активной ревизии: публичный список каналов пустой, не 404/500.
- Роль admin не вынесена в отдельную таблицу: все users из приложения считаются admin.
- Образец XLSX заказчика может отсутствовать: fixture собирается по `docs/xlsx-import-format.md`.
- Тесты: PHPUnit/Pest через `php artisan test`, не Codeception.
- Angular в `frontend/`, не в корне.

## Что сознательно не делается

- саморегистрация и роли кроме guest/admin;
- ручное редактирование тарифов в UI;
- исполнение формул Excel на сервере;
- выдуманные лимиты Yandex;
- смена PostgreSQL.
