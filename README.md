# Habra Open Code

A full-stack clone of the core [habr.com](https://habr.com) experience — publications, hubs, companies, and social activity — built with **Laravel 13** (JSON API) and **Quasar / Vue 3** (SPA frontend).

## Documentation

| Document | Contents |
|---|---|
| [`docs/domain.md`](docs/domain.md) | Domain model: entities, ER diagram, enums, business rules |
| [`docs/api.md`](docs/api.md) | Full reference for every endpoint with examples |
| [`frontend/README.md`](frontend/README.md) | Frontend workflow: dev container, tests, build |

## Stack

**Backend**
- PHP 8.3+, Laravel 13.17
- PostgreSQL (dev via Sail; prod v16)
- Laravel Sanctum — Bearer tokens for the API
- Pest 5 — tests; Larastan 3 (level 5) — static analysis; Pint — code style

**Frontend** (`frontend/`)
- Quasar 2 SPA + TypeScript, Vue Router (history mode), Pinia 4
- axios client with Bearer-token interceptor
- Vitest + @vue/test-utils (unit), Playwright (e2e against a live stack)

## Entities

`User` · `Company` · `Industry` · `Hub` · `Publication` (article/post/news in one table) · `Tag` · `Comment` (nested tree) · `Vote` (morph: publications/comments/karma) · `Bookmark` · `Subscription` (morph: users/hubs/companies) · `Badge`

Relations and voting rules are described in [docs/domain.md](docs/domain.md).

## Quick Start

Requires Docker (Laravel Sail for the backend, plain Docker for the frontend dev server).

```bash
composer install                 # dependencies + vendor/bin/sail
sail up -d                       # backend containers (app, pgsql, redis…)
sail artisan key:generate        # app key (if not set yet)
sail artisan migrate:fresh --seed
```

The API is served at `http://localhost/api`. Smoke check:

```bash
curl http://localhost/api/publications?per_page=2
```

Start the frontend dev server (hot reload, proxies `/api` to the Sail backend):

```bash
docker compose -f docker/dev/frontend.compose.yml up -d
# → http://localhost:9000
```

Demo admin account after seeding:

```
login:  admin
email:  admin@habr.test
password: password
```

All seeded users share the password `password`.

## Commands

Backend (through Sail):

```bash
sail artisan migrate:fresh --seed   # rebuild DB with demo data
sail bin pest                       # tests (64 feature tests)
sail bin pint --dirty               # code style
sail bin phpstan analyse            # static analysis (level 5)
```

Frontend (inside the `frontend-dev` container):

```bash
docker exec -w /app dev-frontend-dev-1 npm test          # unit tests (Vitest)
docker exec -w /app dev-frontend-dev-1 npx vue-tsc --noEmit   # typecheck
docker exec -w /app dev-frontend-dev-1 sh -c \
  "CHROMIUM_PATH=/usr/bin/chromium E2E_BASE_URL=http://localhost:9000 npx playwright test"  # e2e (15 specs)
```

## Key Directory Structure

```
app/
├── Enums/          # PublicationType/Status, Difficulty, Label, VoteSubject…
├── Http/
│   ├── Controllers/Api/   # controllers (Auth, Publication, Vote, Feed…)
│   ├── Requests/          # validation (StorePublicationRequest…)
│   ├── Resources/         # API resources (PublicationResource…)
│   └── Policies/          # author permissions on publication/comment
├── Logging/          # TelegramHandler — error notifications channel
├── Models/           # 11 models
└── Services/         # VoteService, PublicationQueryService
routes/api.php        # 38 endpoints
database/
├── factories/        # factories for all models (+states: published, sandbox…)
├── migrations/       # PostgreSQL schema
└── seeders/          # demo data: hubs, companies, posts, comments, votes
tests/Feature/        # Pest tests grouped by endpoint family
frontend/             # Quasar SPA (see frontend/README.md)
├── src/{pages,components,stores,composables,boot,types}
├── e2e/              # Playwright specs
├── Dockerfile        # multi-stage prod image (node build → static server)
└── server.mjs        # zero-dependency static server with SPA fallback
docker/
├── dev/frontend.compose.yml   # dev containers (vite + e2e browser)
└── prod/                      # Dockerfile, nginx template, php.ini, supervisord
```

## Production

Deployed via `docker-compose.prod.yml`:

| Service | Role |
|---|---|
| `nginx` | TLS entrypoint; routes `/api` + `/up` → PHP-FPM, everything else → frontend |
| `app` | Laravel (PHP-FPM + supervisor); Telegram logging channel for errors |
| `frontend` | Node.js serving the built SPA with history-mode fallback |
| `postgres`, `redis` | data stores |
| `certbot` | certificate renewal |

Deploy flow: push to `main` → on the server `git pull && docker compose -f docker-compose.prod.yml build app frontend && docker compose -f docker-compose.prod.yml up -d`.
