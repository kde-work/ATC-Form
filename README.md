# ATC Express Calculator

Калькулятор стоимости доставки ATC Express: публичный UI и admin-панель импорта тарифов.

- Backend: PHP 8.5, Laravel, MySQL 8.4, nginx (Docker Compose)
- Frontend: Angular (standalone) в каталоге `frontend/`
- API: REST JSON, `/api/v1/*`, деньги и веса строками
- Auth: Laravel Sanctum (Bearer), один admin из seeder

## Быстрый старт

### 1. Hosts

Добавьте в hosts (Windows: `C:\Windows\System32\drivers\etc\hosts`):

```text
127.0.0.5 atc.form
```

`APP_BIND_IP` по умолчанию `127.0.0.5` (см. `.env.example`).

### 2. Env

```bat
copy .env.example .env
docker compose run --rm php php artisan key:generate
```

Задайте `ADMIN_EMAIL` и `ADMIN_PASSWORD`.

### 3. Поднять стек

```bat
docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --force
```

### 4. Собрать Angular (обязательно для http://atc.form)

```bat
cd frontend
npm ci
npm run build
cd ..
docker compose up -d --force-recreate nginx
```

Или из корня: `npm run build:frontend`, затем recreate nginx.

Открыть: [http://atc.form](http://atc.form) → редирект на `/calculator`.

API: [http://atc.form/api/v1/platforms](http://atc.form/api/v1/platforms).

## Frontend: два режима

### Prod / same-origin (рекомендуется локально через nginx)

1. `cd frontend && npm run build`
2. nginx отдаёт `frontend/dist/frontend/browser`
3. `/api`, `/images`, favicon/manifest — из Laravel `public/`
4. `apiBaseUrl`: `/api/v1` (same origin, CORS не нужен)

Без сборки `http://atc.form/` вернёт 404 SPA (`index.html` отсутствует).

### Dev: Angular dev server

На хосте:

```bat
cd frontend
npm start
```

Открыть `http://127.0.0.1:4200`. Proxy: `frontend/proxy.conf.json` → `http://atc.form`.

В Docker (profile `frontend`):

```bat
docker compose --profile frontend up -d node
```

`working_dir`: `frontend/`, порт `ANGULAR_PORT` (default 4200), proxy: `proxy.conf.docker.json` → сервис `nginx`.

## Вход в admin

- UI: [http://atc.form/admin/login](http://atc.form/admin/login)
- Учётка: `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env`
- После seed: импортов/активных тарифов может не быть — загрузите fixture XLSX и Activate

## Переменные окружения

| Переменная | Назначение |
|---|---|
| `APP_URL` / `APP_BIND_IP` / `APP_PORT` | URL и bind nginx |
| `DB_*` | MySQL |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Seeder admin |
| `TARIFF_IMPORT_MAX_BYTES` | Лимит XLSX (default 5 MB) |
| `ANGULAR_PORT` | Порт ng serve в profile `frontend` |
| `SANCTUM_STATEFUL_DOMAINS` | Cookie-домены (API в UI использует Bearer) |

Полный список: `.env.example`.

## Тесты

Backend (на хосте PHP 8.4+/8.5 или в контейнере):

```bat
docker compose exec php php artisan test
```

Или локально: `php85 vendor/bin/phpunit` / `php artisan test` (если PHP подходит).

Frontend:

```bat
cd frontend
npm test -- --watch=false
npm run build
```

## Деплой на хостинг (SFTP)

Креды: `.vscode/sftp.json` (`host`, `port`, `username`, `password`, `remotePath`). Нужны Node.js и OpenSSH `scp`.

```bat
scripts\deploy-last-commit.bat
scripts\deploy-frontend.bat
```

- `deploy-last-commit.bat` — файлы из `app/` последнего git-коммита на сервер
- `deploy-frontend.bat` — `frontend/dist/frontend/browser/*` → `public/` на сервере (сначала `cd frontend && npm run build`)

## XLSX импорт

Правила парсинга, статусы и примеры ошибок: [docs/xlsx-import-format.md](docs/xlsx-import-format.md).

Тестовые файлы: `tests/Fixtures/xlsx/` (`valid-tariffs.xlsx` и др.).

## Допущения Yandex Market

- Тарифная модель Yandex опирается на физический вес; габариты в калькуляторе необязательны и по умолчанию скрыты.
- Фиктивные лимиты для Yandex не выдумываются: в БД только то, что пришло из XLSX / seed.
- Курс RUB→CNY для Yandex показывается в результате; guest курс не меняет.

## Примеры API

OpenAPI: [docs/openapi.yaml](docs/openapi.yaml).

```bat
curl -s http://atc.form/api/v1/platforms
```

```bat
curl -s -X POST http://atc.form/api/v1/calculations -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"platform\":\"ozon\",\"delivery_channel_code\":\"atc-standard-big\",\"physical_weight_grams\":\"2500\",\"length_cm\":\"100\",\"width_cm\":\"40\",\"height_cm\":\"30\",\"order_cost\":\"500\",\"order_cost_currency\":\"CNY\"}"
```

```bat
curl -s -X POST http://atc.form/api/v1/admin/login -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"email\":\"admin@atc.form\",\"password\":\"password\"}"
```

## Документация

- [docs/architecture.md](docs/architecture.md) — модули и схема данных
- [docs/xlsx-import-format.md](docs/xlsx-import-format.md) — Excel
- [docs/openapi.yaml](docs/openapi.yaml) — контракт API
- `.tmp/task/` — внутренняя постановка и отчёты этапов (не продукт)

## Известные ограничения

- Импорт XLSX в MVP синхронный в HTTP (код готов к Job/очереди).
- Excel-формулы и VBA на сервере не исполняются.
- PostgreSQL не используется (MySQL 8.4).
- Саморегистрации и ролей кроме guest/admin нет.
- Ручного редактирования тарифов в UI нет — только импорт.
- Vite в корне остаётся от скелета Laravel; UI приложения — Angular в `frontend/`.
