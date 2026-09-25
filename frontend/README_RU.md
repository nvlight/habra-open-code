# Habra Open Code — Фронтенд

Quasar 2 SPA (TypeScript) для API Habra Open Code: лента, публикации с вложенными комментариями и голосованием, хабы, компании, профили пользователей, редактор, закладки, персональная лента.

## Разработка

Node.js на хосте не нужен — всё работает в Docker. Dev-контейнер входит в сеть Sail и проксирует `/api` на Laravel-бэкенд (`http://laravel.test`), поэтому настройка CORS не нужна.

```bash
# из корня репозитория; требует поднятый Sail-бэкенд
docker compose -f docker/dev/frontend.compose.yml up -d
# → http://localhost:9000
```

Вход под засеянным аккаунтом (`admin` / `password`).

## Скрипты

Все команды выполняются внутри работающего dev-контейнера (`dev-frontend-dev-1`):

```bash
docker exec -w /app dev-frontend-dev-1 npm test          # юнит-тесты Vitest
docker exec -w /app dev-frontend-dev-1 npx vue-tsc --noEmit   # проверка TypeScript

docker exec -w /app dev-frontend-dev-1 sh -c \
  "CHROMIUM_PATH=/usr/bin/chromium E2E_BASE_URL=http://localhost:9000 npx playwright test"
```

E2E-спеки лежат в [`e2e/`](e2e/) и работают против реального стека (Sail-бэкенд + vite dev-сервер). Используется системный chromium через `CHROMIUM_PATH`; `E2E_BASE_URL` по умолчанию — `http://frontend-dev:9000` (адрес в сети).

## Структура

```
src/
├── boot/axios.ts        # axios-инстанс: Bearer-токен, обработка 401/5xx
├── composables/         # usePublicationFeed() — общая логика списка/пагинации
├── stores/              # Pinia: auth, subscriptions
├── router/              # маршруты в history mode + auth-guard
├── layouts/             # шапка/каркас в стиле habr
├── pages/               # Feed, Publication, User, Hub, Company, Editor…
├── components/          # PublicationCard, VoteArrows, CommentTree…
├── types/api.ts         # типизированные модели API (включая конверты пагинации)
└── css/                 # тема в стиле habr
e2e/                     # Playwright-спеки
server.mjs               # прод-статический сервер без зависимостей + SPA fallback
Dockerfile               # многостадийный prod-образ (node build → node runtime)
```

## Темы

Дизайн-токены извлечены из официальной темы habr.com (`light-v2.css` / `dark-v2.css`) и живут в `src/css/quasar.variables.scss` как CSS-custom-свойства (`--habr-*`) для светлой и тёмной палитр.

- **Авто-тёмная**: при старте `src/boot/theme.ts` следует `prefers-color-scheme` и дальше слушает изменения на уровне ОС.
- **Ручное переключение**: кнопка ☀/🌙/AUTO в шапке циклирует `auto → light → dark`; выбор сохраняется в localStorage (ключ `theme`).
- Тёмный режим Quasar управляется через `Dark.set()`, который переключает `body--dark` — все кастомные классы (`.habr-card`, `.pub-title`, `.vote-arrow`, …) читают токены и перекрашиваются автоматически.
- Шрифты: **Fira Sans** (UI) и **Inter** (текст статей), самохостятся через `@fontsource/*` — без внешних CDN, кириллица включена.

## Правило API-обёртки

Каждый списочный эндпоинт отдаёт `{ data: [...], links, meta }`; одиночные ресурсы обёрнуты `{ data: { … } }`. Разворачивай перед использованием и защищай массивы `Array.isArray(...)` — см. `usePublicationFeed()`.

## Продакшен-сборка

Образ собирается из этой папки корневым `docker-compose.prod.yml` (сервис `frontend`):

```bash
docker compose -f ../docker-compose.prod.yml build frontend
```

Многостадийность: `npm ci --ignore-scripts` → `quasar prepare && quasar build` → в рантайм копируется `dist/spa` и раздаётся через `server.mjs` (иммутабельный кэш для `/assets/*`, SPA-fallback для всего остального).