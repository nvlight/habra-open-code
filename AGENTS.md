# AGENTS.md

## Project Overview

Full-stack clone of habr.com core entities (publications, hubs, companies, social activity): **Laravel 13** JSON API + **Quasar / Vue 3 SPA** frontend.

## Tech Stack

**Backend**
- **PHP**: 8.3+ / Laravel 13.17
- **Database**: PostgreSQL (dev via Sail, prod v16); tests run against pgsql `testing` database with `RefreshDatabase`
- **Auth**: Laravel Sanctum (Bearer tokens)
- **Testing**: Pest 5 (`tests/Feature`, RefreshDatabase enabled globally in `tests/Pest.php`)
- **Static analysis**: Larastan 3 level 5 — must stay at 0 errors
- **Code style**: Laravel Pint

**Frontend** (`frontend/`)
- Quasar 2 SPA, TypeScript strict, Vue Router 5 (history mode), Pinia 4, axios
- Unit tests: Vitest + @vue/test-utils (jsdom), config in `vitest.config.ts`
- E2E: Playwright (`frontend/e2e/`), runs against the live dev stack; chromium comes from the system package via `CHROMIUM_PATH=/usr/bin/chromium`

## Domain Model

11 models (`app/Models/`): `User`, `Company`, `Industry`, `Hub`, `Publication` (single table for article/post/news), `Tag`, `Comment` (nested via `parent_id`), `Vote` (morph `voteable`: Publication|Comment|User-karma), `Bookmark`, `Subscription` (morph `subscribable`: User|Hub|Company), `Badge`.

Enums live in `app/Enums/` and are cast in models: `PublicationType`, `PublicationStatus` (draft/sandbox/published), `Difficulty`, `PublicationLabel`, `VoteSubject`, `SubscribableType`.

Full docs: `docs/domain.md` (ER diagram, business rules), `docs/api.md` (all endpoints).

## Running

Backend — all artisan/vendor commands go through **Sail** (no host PHP):

```bash
sail artisan migrate:fresh --seed   # rebuild DB with demo data
sail bin pest                       # tests
sail bin pint --dirty               # style fix
sail bin phpstan analyse            # static analysis
```

Frontend — no host Node required; everything runs in containers:

```bash
docker compose -f docker/dev/frontend.compose.yml up -d     # vite dev server → http://localhost:9000
docker exec -w /app dev-frontend-dev-1 npm test             # unit tests
docker exec -w /app dev-frontend-dev-1 npx vue-tsc --noEmit # typecheck
docker exec -w /app dev-frontend-dev-1 sh -c \
  "CHROMIUM_PATH=/usr/bin/chromium E2E_BASE_URL=http://localhost:9000 npx playwright test"
```

The dev container joins the Sail network and proxies `/api` to `http://laravel.test` (same origin — no CORS).

## Development Conventions

### Backend

- PHP 8.3; 4-space indent, LF, final newline
- Models use PHP 8 attributes `#[Fillable([...])]` / `#[Hidden([...])]` instead of properties
- Every model has a `@property` docblock listing columns with proper types (enums, Carbon, bool) — required for Larastan level 5 attribute inference
- Relations use explicit return types (`HasMany`, `BelongsTo`, `MorphMany`…)
- Validation in FormRequests; responses through JsonResources (`app/Http/Resources/`)
- Authorization via Policies (`PublicationPolicy`, `CommentPolicy`) + base Controller uses `AuthorizesRequests`
- Business logic that touches counters lives in services (`app/Services/VoteService.php`, `PublicationQueryService.php`)
- Denormalized counters (`rating`, `comments_count`, `bookmarks_count`, `subscribers_count`) are recalculated on write, never trusted from client input

### Frontend

- TypeScript strict; imports use the `@/` alias (scaffold convention, not Quasar's legacy `src/`)
- **API envelope rule**: every list endpoint returns `{ data: [...], links, meta }`; single resources are wrapped as `{ data: { … } }`. Always unwrap before use and guard with `Array.isArray(...)` — see any page using `usePublicationFeed()`
- Shared feed logic goes through the `usePublicationFeed()` composable; subscription state lives in `stores/subscriptions.ts`
- Interactive elements get explicit `data-testid` attributes — Playwright specs rely on them
- New API endpoints: verify the actual response shape with curl **before** writing frontend code

## Known Pitfalls

### Backend

- **fresh() after create**: a freshly `create()`d model does NOT carry DB column defaults (`rating` stays `null` in responses). Return `$model->fresh([...])` — but note this loses `wasRecentlyCreated`, so JsonResource responds 200 instead of 201; add `->response()->setStatusCode(201)`.
- **Public routes & auth**: without the `auth:sanctum` middleware `$request->user()` is always null, even with a valid Bearer token. Use `$request->user('sanctum')` when a public route needs optional identity (e.g. draft visibility).
- **Nested relations in resources**: `whenLoaded()` drops keys that were not eager-loaded. Load authors at every nesting level (`replies.author`, `replies.replies.author`, …) or nested JSON silently loses fields.
- **Pivot naming**: BelongsToMany without an explicit table infers alphabetically sorted names — `hub_publication`, not `publication_hub`. Keep migrations consistent.
- **Ordering**: `scopePublished()` already adds `orderByDesc(published_at)`. To re-sort by rating use `$query->reorder()->orderByDesc('rating')` — chained `orderBy` alone becomes secondary sort.
- **Stringable comparisons**: `$request->string('sort') === 'best'` is always false (object vs string). Cast first: `(string) $request->string('sort', 'new')`.
- **Tests & auth**: within one test method Sanctum's `RequestGuard` memoizes the user across requests. After revoking a token call `$this->app->make('auth')->forgetGuards()` before asserting 401.
- **Mass assignment**: counter columns are not fillable; use `forceFill()` when setting them in tests/seeds.
- **Registration flag lives in cache**: `registration:disable/enable` store the flag via `Cache::forever`. Running `cache:clear` / `optimize:clear` silently re-enables registration — re-run the command after clearing the cache.
- **Legacy docker builder + unreadable context files**: without the buildx plugin the legacy builder aborts the whole build when the context contains unreadable files (root-owned `letsencrypt/archive/*.pem` after a renewal). `.dockerignore` at the repo root keeps `docker/prod/letsencrypt`, `.env`, `.git` and `vendor` out of the context — never remove those lines, also because `COPY . .` would otherwise bake secrets into images.
- **`.env` vs config cache**: if `bootstrap/cache/config.php` exists in the container, `.env` edits are ignored until it is deleted and `config:clear` runs. Verify with `php artisan config:show`.
- Faker `unique()->word()` overflows fast in seeders — dedupe tags via `firstOrCreate(['name' => fake()->word()])`.

### Frontend

- **vue-router 4+**: custom regexes in paths (`:id(\d+)`) are not supported — use plain params.
- **Quasar scaffold aliases**: generated code imports via `@/…`; mixing legacy `src/`/bare paths breaks Vite resolution.
- **Prod build is stricter than dev**: Vite dev tolerates duplicate attributes and unresolved imports until the page is opened, but `quasar build` (Rolldown) fails the whole bundle. Run a prod build or at least `vue-tsc` before pushing.
- **Quasar palette class collisions**: `.text-secondary` etc. are generated from the palette (`$secondary` = green here). Custom semantic classes must not reuse palette names — the project uses `.text-dim`, `.text-link`, `.panel-card`.
- **Dockerfile build**: `npm ci` must run with `--ignore-scripts` — the `quasar prepare` postinstall fails before `quasar.config.ts` is copied into the layer.
- **Playwright on Alpine**: install system chromium (`apk add chromium`) and point Playwright at it via `CHROMIUM_PATH`; do not pull the multi-GB official image.

## Testing

### Backend

- Feature tests per endpoint group in `tests/Feature/` (Auth, Publication, Comment, Vote, Bookmark, Subscription, Feed)
- Factories have states: `published()`, `sandbox()`, `draft()`, `news()`, `post()`, `translation()`, `corporate()`
- Run before committing: `sail bin pest && sail bin pint --dirty && sail bin phpstan analyse`

### Frontend

- Unit tests next to the code they cover (`src/**/*.test.ts`): stores are tested with mocked axios (`vi.mock('@/boot/axios')`), components via `@vue/test-utils`
- E2E specs live in `frontend/e2e/` and run against the real dev stack (Sail backend + vite); login with seeded `admin/password`
- Run before committing: `npm test && npx vue-tsc --noEmit` + the playwright command above
