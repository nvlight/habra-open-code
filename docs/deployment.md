# Deployment & Operations Runbook

Production runs on a single VPS via `docker-compose.prod.yml`. This document covers the topology, the TLS lifecycle, monitoring, and the failure modes we have actually hit.

## Topology

```
Internet ──► nginx (80: 301 → https; 443: TLS)
              ├── /api, /up                     → app (PHP-FPM :9000)
              ├── /.well-known/acme-challenge/  → acme_webroot volume
              └── /                             → frontend (Node :3000, SPA + fallback)
```

| Service | Image | Role |
|---|---|---|
| `nginx` | nginx:alpine | TLS entrypoint; serves ACME challenges; routes API to PHP-FPM, everything else to the SPA |
| `app` | built from `docker/prod/Dockerfile` | Laravel (PHP-FPM + supervisord: queue worker, scheduler); Telegram logging channel |
| `frontend` | built from `frontend/Dockerfile` | Multi-stage node build → Node runtime serving `dist/spa` (zero npm deps in runtime) |
| `postgres` / `redis` | official images | data stores (healthchecked) |
| `certbot` | certbot/certbot | renewal loop: `certbot renew` every 12 h |

Shared volumes: `app_storage`, `app_bootstrap`, `postgres_data`, `redis_data`, `acme_webroot` (ACME challenges), host bind `docker/prod/letsencrypt` (certificates, private!).

## Deploy flow

```bash
git push origin main                      # from the dev machine
# on the server:
cd /opt/app && git pull origin main
docker compose -f docker-compose.prod.yml build app frontend
docker compose -f docker-compose.prod.yml up -d
```

Code is baked into the images — a `git pull` without `build` changes nothing. If the build output looks suspicious, check `docker images <image> --format '{{.CreatedSince}}'`.

## TLS certificates (Let's Encrypt, shortlived profile)

- Profile: `shortlived` — certificates live **7 days**.
- Authenticator: **webroot**. The certbot loop writes challenges into the `acme_webroot` volume; nginx serves them at `/.well-known/acme-challenge/` (priority location in both `:80` and `:443` server blocks). nginx is never stopped for renewals.
- `renew_before_expiry = 3 days` is set in `/etc/letsencrypt/renewal/5.188.31.27.conf` (inside the `letsencrypt` volume). Without it certbot would attempt renewal on every 12-hour run and hit Let's Encrypt's duplicate-certificate limit (5/week) for a 7-day certificate.
- The renewal command in the compose entrypoint carries `--webroot -w /var/www/certbot` explicitly, so the flags survive even if the renewal conf is regenerated.

### Manual operations

```bash
# force renewal now
cd /opt/app && docker compose -f docker-compose.prod.yml exec certbot \
  certbot renew --webroot -w /var/www/certbot --force-renewal

# nginx caches the certificate in memory — reload after any renewal
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload

# inspect current dates
docker exec app-certbot-1 openssl x509 \
  -in /etc/letsencrypt/live/5.188.31.27/fullchain.pem -noout -dates
```

### Expiry monitoring

`php artisan cert:check` (scheduled daily at 09:00 UTC via the supervisor scheduler loop) connects to `nginx:443`, parses the served certificate and:

- logs `ERROR` to the Telegram channel when ≤ 3 days remain (matches certbot's `renew_before_expiry = 3 days`, so the alert fires only if renewal itself is failing; override with `--min-days=N`) or the certificate is expired/unreachable;
- prints an informational line otherwise.

The Telegram channel receives everything logged at `error` level through the custom `telegram` log channel (`app/Logging/TelegramHandler.php`, configured by `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` in `.env`).

## User registration toggle

```bash
docker compose -f docker-compose.prod.yml exec app php artisan registration:disable
docker compose -f docker-compose.prod.yml exec app php artisan registration:enable
```

The flag lives in the cache (database driver → survives restarts). While disabled, `POST /api/auth/register` responds `403 {"message": "Регистрация временно приостановлена"}`.

## Troubleshooting (real incidents)

### Certificate expired, browser shows `ERR_CERT_DATE_INVALID`

1. `docker logs --tail 40 app-certbot-1` — the renewal error is here (host has no log file; certbot logs to stdout).
2. Historical root cause: `standalone` authenticator could never renew (its challenge server was unreachable; nginx served the SPA to Let's Encrypt). Fixed by switching to webroot — do not revert.
3. After any successful renewal: `nginx -s reload` (or restart the nginx container), otherwise nginx keeps serving the old certificate from memory.
4. Verify from outside: `curl -sI https://5.188.31.27/` (no `-k`) — must not fail TLS.

### Build fails with `checking context: no permission to read …` or silently produces a stale image

The legacy builder (no buildx plugin on the VPS) aborts when the build context contains unreadable files — e.g. root-owned `letsencrypt/archive/*-privkey*.pem` that appeared after a renewal. `.dockerignore` at the repo root keeps `docker/prod/letsencrypt` out of the context; do not remove that line. It also keeps `.env`, `.git`, `vendor` and node_modules out — `COPY . .` must never bake secrets or local dependencies into images.

### `.env` changed but the app ignores the new values

PHP reads `.env` only when the config cache is built. If `bootstrap/cache/config.php` exists inside the container, edit `.env`, then:

```bash
docker compose -f docker-compose.prod.yml exec app sh -c "rm -f bootstrap/cache/config.php && php artisan config:clear"
docker compose -f docker-compose.prod.yml restart app
```

Verify with `php artisan config:show <channel>` before assuming the change took effect.

### `cache:clear` re-enables registration

The registration flag lives in the cache. After any cache flush re-run `registration:disable`.

## Security notes

- Private keys never enter the build context or images (`docker/prod/letsencrypt` is dockerignored). If an old image predates this rule, prune it: `docker image prune -a -f`.
- Rotate the bot token via @BotFather after any leak; update `TELEGRAM_BOT_TOKEN` in `.env` and clear the config cache (see above).
- Postgres/Redis ports are not published to the host; only nginx (80/443) is exposed.
