# Habra Open Code — Frontend

Quasar 2 SPA (TypeScript) for the Habra Open Code API: feed, publications with nested comments and voting, hubs, companies, user profiles, editor, bookmarks, personal feed.

## Development

No host Node.js required — everything runs in Docker. The dev container joins the Sail network and proxies `/api` to the Laravel backend (`http://laravel.test`), so there is no CORS setup.

```bash
# from the repository root; requires the Sail backend to be up
docker compose -f docker/dev/frontend.compose.yml up -d
# → http://localhost:9000
```

Login with a seeded account (`admin` / `password`).

## Scripts

All commands run inside the running dev container (`dev-frontend-dev-1`):

```bash
docker exec -w /app dev-frontend-dev-1 npm test          # Vitest unit tests
docker exec -w /app dev-frontend-dev-1 npx vue-tsc --noEmit   # TypeScript check

docker exec -w /app dev-frontend-dev-1 sh -c \
  "CHROMIUM_PATH=/usr/bin/chromium E2E_BASE_URL=http://localhost:9000 npx playwright test"
```

E2E specs live in [`e2e/`](e2e/) and run against the real stack (Sail backend + vite dev server). System chromium is used via `CHROMIUM_PATH`; `E2E_BASE_URL` defaults to `http://frontend-dev:9000` (in-network address).

## Structure

```
src/
├── boot/axios.ts        # axios instance: Bearer token, 401/5xx handling
├── composables/         # usePublicationFeed() — shared list/pagination logic
├── stores/              # Pinia: auth, subscriptions
├── router/              # history-mode routes + auth guards
├── layouts/             # habr-style header/layout
├── pages/               # Feed, Publication, User, Hub, Company, Editor…
├── components/          # PublicationCard, VoteArrows, CommentTree…
├── types/api.ts         # typed API models (pagination envelopes included)
└── css/                 # habr-like theme
e2e/                     # Playwright specs
server.mjs               # zero-dependency prod static server + SPA fallback
Dockerfile               # multi-stage prod image (node build → node runtime)
```

## Theming

Design tokens are extracted from habr.com's official theme CSS (`light-v2.css` / `dark-v2.css`) and live in `src/css/quasar.variables.scss` as CSS custom properties (`--habr-*`) for both light and dark palettes.

- **Auto dark mode**: on startup `src/boot/theme.ts` follows `prefers-color-scheme` and keeps listening for OS-level changes.
- **Manual override**: the ☀/🌙/AUTO button in the header cycles `auto → light → dark`; the choice persists in localStorage (`theme` key).
- Quasar dark mode is driven via `Dark.set()`, which toggles `body--dark` — all custom classes (`.habr-card`, `.pub-title`, `.vote-arrow`, …) read the tokens and restyle automatically.
- Fonts: **Fira Sans** (UI) and **Inter** (article text), self-hosted via `@fontsource/*` packages — no external CDN, cyrillic subsets included.

## API envelope rule

Every list endpoint returns `{ data: [...], links, meta }`; single resources are wrapped as `{ data: { … } }`. Unwrap before use and guard arrays with `Array.isArray(...)` — see `usePublicationFeed()`.

## Production build

The image is built from this folder by the root `docker-compose.prod.yml` (`frontend` service):

```bash
docker compose -f ../docker-compose.prod.yml build frontend
```

Multi-stage: `npm ci --ignore-scripts` → `quasar prepare && quasar build` → runtime copies `dist/spa` and serves it with `server.mjs` (immutable cache for `/assets/*`, SPA fallback for everything else).
