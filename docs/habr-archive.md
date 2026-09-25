# habr.com Archive Pipeline

An offline archive of habr.com: a full list of publication URLs together with the raw JSON of every article, stored locally. It runs through the queue (`rabbitmq`), so it needs workers to be up.

## What it does

- **Discover** — walks the habr sitemap (`https://habr.com/sitemap*`) and collects every `/ru/` URL whose `lastmod` is ≥ `2026-01-01` (configurable), skipping duplicate `source_id`s. One row per URL is written to the `habr_sources` table with the normalized type: `article` / `news` / `post` / `special` (company content keeps its `company_slug`).
- **Fetch** — downloads each article's raw JSON from the internal kek API (`https://habr.com/kek/v2/publications/{id}?fl=*.en%2CrobotsTxt`) and stores it at `storage/app/habr/{source_id}.json`. The article's exact publish date (`timePublished`) is saved to `published_at`.
- **Count** — the authoritative number of publications published since a date: `habr:count --since=2026-01-01` (based on real `published_at`, not URL heuristics).

Scale reference: a full discover on 2026-01-01 produced **57 462** rows (`~/982 250` source id ≈ the 2026 boundary).

### Source statuses

| Status | Meaning |
|---|---|
| `pending` | discovered, queued for fetching |
| `fetched` | saved to `storage/app/habr/{id}.json` |
| `failed` | fetch threw (rate limit exhausted, origin 403/5xx, …) |
| `excluded` | origin returned `404` — the article is gone (list only, no article) |

## Commands

Run through Sail (no host PHP):

```bash
sail artisan habr:discover --since=YYYY-MM-DD   # (re)collect URL list from the sitemap
sail artisan habr:fetch --limit=500             # fetch N remaining sources (default 500)
sail artisan habr:retry-failed                  # reset all failed → pending
sail artisan habr:count --since=YYYY-MM-DD      # count sources with published_at ≥ date
```

`habr:retry-failed` only resets the status; the re-fetch itself happens on the next dispatch (daily 03:20/03:30 or a manual run).

## Queue: how to start it

Dev runs no supervisor — workers and the scheduler are `docker compose exec -d` processes and **die whenever the containers restart** (deploys, reboots, power loss). Start them:

```bash
# 4 parallel workers consuming the "default" queue on RabbitMQ
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120

# daily scheduled jobs (03:00/03:20/03:30) — one instance only
docker compose exec -d laravel.test php artisan schedule:work
```

Connection config lives in `.env`: `QUEUE_CONNECTION=rabbitmq`, `RABBITMQ_HOST=rabbitmq`, `RABBITMQ_USER=sail`, `RABBITMQ_PASSWORD=password`. The connection is passed to `queue:work` **positionally** (`rabbitmq`), there is no `--connection` option.

Sanity checks:

```bash
docker compose exec laravel.test sh -c 'ps aux | grep -c "[q]ueue:work rabbitmq"'   # → 4
docker compose exec laravel.test sh -c 'ps aux | grep -c "[s]chedule:work"'         # → 1
# queue depth via the management API (vhost "/", user sail/password):
curl -s -u sail:password http://localhost:15672/api/queues/%2F/default | grep messages
```

### After a reboot / container restart

1. Containers up: `docker compose up -d` (Sail network) — **workers and scheduler are gone**, restart them as above.
2. RabbitMQ restarted → its queue is empty; the ~2000 in-flight messages vanish, but nothing is lost: rows stay `pending` in PostgreSQL.
3. Resume the archive from the admin panel (button **«Разовая загрузка контента»**) or:
   `curl -X POST http://localhost/api/admin/habr/fetch -H "Authorization: Bearer $TOKEN" -d '{"limit":100000}'`

## Daily schedule

`routes/console.php` (dev `schedule:work`; prod supervisord runs the same scheduler):

| When (UTC+0) | What |
|---|---|
| 03:00 | `DiscoverHabrUrlsJob` — pick up new URLs from the sitemap |
| 03:20 | `habr:retry-failed` — requeue yesterday's failures |
| 03:30 | `DispatchHabrFetchJob` — enqueue fetching of all remaining `pending` |

## Stop / resume semantics

The admin panel can stop a running crawl (or `POST /api/admin/habr/stop`). Stopping:

1. sets a cancel flag (`habr:crawl:cancel` cache key);
2. best-effort purges the RabbitMQ queue via the management API (instant stop; the credential pair is `sail/password`).

In-flight fetches finish their current article; every job re-checks the flag before dispatching/re-dispatching, so nothing new is enqueued. Already `fetched` rows are untouched and everything else stays `pending` — **no data is lost by stopping**.

Resuming is just another `POST /api/admin/habr/fetch` (or the admin button): `start()` clears the cancel flag **and** the crawl cursor, then the dispatch cascade re-enqueues all `pending` from the top.

## Dispatch cascade and the cursor

`DispatchHabrFetchJob` feeds the queue in batches of **2000** `source_id`s and re-dispatches itself after every batch, walking the `pending` set with a **cursor** (`habr:crawl:cursor` cache key = last dispatched id) instead of an SQL offset:

```php
HabrSource::query()->where('source_id', '>', $cursor)->pending()
    ->orderBy('source_id')->limit(2000)->pluck('source_id');
```

The offset-based predecessor stalled mid-run: it stepped 0/2000/4000… over a set that shrank while workers fetched, so an offset could run past the (now smaller) table and the cascade concluded "done" with ~22 k rows still pending. The cursor tolerates a shrinking `pending` set and never re-dispatches an id twice.

## Rate limiting and failures

- origin `429` → `HabrRateLimitedException`; the job re-dispatches itself with `delay(max(Retry-After, 5))` and exponential backoff (`tries = 3`, `backoff = [10, 30, 120]`). The source stays `pending`.
- origin `404` → status `excluded` (row kept: it documents the URL's existence).
- anything else (origin `403`/`5xx`, network, JSON decode) → throws → job lands in `failed_jobs`, status `failed`; recovered by `habr:retry-failed` or the daily 03:20.

At sustained load (tens of thousands of requests over ~2 h) habr starts returning `403` on a slice of requests — those become `failed` and are mopped up by the daily retry; it does not affect the final archive.

## Cache keys

Crawl state lives in the Laravel cache (Redis in dev): `habr:crawl:cancel`, `habr:crawl:cursor`, `habr:crawl:queued_at`, `habr:crawl:last_activity`. A `cache:clear` wipes them — a fresh `POST /fetch` re-establishes the session, but active workers would keep heartbeat-writing anyway.

## Admin API

The endpoints and JSON shapes are documented in [`api.md`](api.md#admin-habrcom-archive).