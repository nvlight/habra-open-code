# Руководство по развёртыванию и эксплуатации

Продакшен живёт на одном VPS через `docker-compose.prod.yml`. Этот документ описывает топологию, жизненный цикл TLS, мониторинг и реально ловившиеся режимы отказа.

## Топология

```
Internet ──► nginx (80: 301 → https; 443: TLS)
              ├── /api, /up                     → app (PHP-FPM :9000)
              ├── /.well-known/acme-challenge/  → acme_webroot volume
              └── /                             → frontend (Node :3000, SPA + fallback)
```

| Сервис | Образ | Роль |
|---|---|---|
| `nginx` | nginx:alpine | TLS-вход; обслуживает ACME-челленджи; маршрутизирует API на PHP-FPM, всё остальное — на SPA |
| `app` | собирается из `docker/prod/Dockerfile` | Laravel (PHP-FPM + supervisord: воркер очереди, scheduler); Telegram-канал логов |
| `frontend` | собирается из `frontend/Dockerfile` | Многостадийная node-сборка → Node runtime раздаёт `dist/spa` (ноль npm-зависимостей в рантайме) |
| `postgres` / `redis` | официальные образы | хранилища данных (с healthcheck) |
| `certbot` | certbot/certbot | цикл продления: `certbot renew` каждые 12 ч |

Общие volume: `app_storage`, `app_bootstrap`, `postgres_data`, `redis_data`, `acme_webroot` (ACME-челленджи), host bind `docker/prod/letsencrypt` (сертификаты, приватно!).

## Процесс деплоя

Пуши в `main` авто-деплоятся через GitHub Actions (`.github/workflows/deploy.yml`):

```bash
git pull origin main
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan event:cache
docker compose -f docker-compose.prod.yml restart app
```

Код запекается в образы — `git pull` без `build` ничего не меняет. Если результат сборки подозрителен, проверь `docker images <image> --format '{{.CreatedSince}}'`.

> Зачем финальный `restart app`? Сразу после `up -d` supervisord поднимает воркер
> очереди и scheduler при старте контейнера. Они проверяют кэшированный
> `routes-v7.php` (он ещё есть в постоянном volume `app_bootstrap`) и откладывают
> `require`. Если последующий `route:cache` удалит этот файл в этом узком окне,
> старт упадёт с `require(.../routes-v7.php): Failed to open stream`. Рестарт даёт
> каждому воркеру старт строго *после* финализации кэшей и снимает гонку. То же
> касается любого ручного `optimize`/`route:cache` при работающем приложении.

## TLS-сертификаты (Let's Encrypt, shortlived-профиль)

- Профиль: `shortlived` — сертификаты живут **7 дней**.
- Авторизатор: **webroot**. Цикл certbot пишет челленджи в volume `acme_webroot`; nginx отдаёт их по `/.well-known/acme-challenge/` (приоритетная локация в обоих server-блоках `:80` и `:443`). nginx для продлений никогда не останавливается.
- `renew_before_expiry = 3 days` задан в `/etc/letsencrypt/renewal/5.188.31.27.conf` (внутри volume `letsencrypt`). Без него certbot пытался бы продлеваться на каждом 12-часовом прогоне и упирался бы в лимит дублей Let's Encrypt (5/нед) для 7-дневного сертификата.
- Команда продления в entrypoint compose несёт `--webroot -w /var/www/certbot` явно, чтобы флаги пережили даже перегенерацию renewal-conf.

### Автоматический reload nginx после продления

nginx кэширует сертификат в памяти и **продолжает отдавать старый**, даже когда certbot пишет новый в общий bind `letsencrypt`. Продление без reload — историческая первопричина алертов «сертификат скоро истечёт» (`cert:check` читает то, что отдаёт nginx, поэтому пропущенный reload выглядит как неудачное продление).

На хосте работает **systemd-таймер, перезагружающий nginx при смене отпечатка сертификата на диске**:

- Скрипт `docker/prod/reload-nginx-on-renew.sh` (пользователь `dk`, входит в группу `docker`): сравнивает sha256 отпечаток `live/5.188.31.27/fullchain.pem` с последним перезагруженным, сохранённым в `docker/prod/.cert-reload-state`; при изменении запускает `docker compose exec nginx nginx -s reload` и записывает новый отпечаток.
- Юниты `cert-nginx-reload.{service,timer}` (в репозитории: `docker/prod/systemd/`, установлены в `~dk/.config/systemd/user/`) запускают скрипт каждые 30 мин (`cert-nginx-reload.timer`, enabled, без root благодаря `loginctl enable-linger dk`).
- Скрипт пишет состояние только после успешного reload, поэтому если nginx на миг лежал (например, посреди деплоя), на следующем тике он повторится.

Значит, **вручную nginx после продления перезагружать обычно не нужно**. Когда что-то выглядит странно — ручные команды ниже, как fallback.

### Ручные операции

```bash
# принудительное продление сейчас
cd /opt/app && docker compose -f docker-compose.prod.yml exec certbot \
  certbot renew --webroot -w /var/www/certbot --force-renewal

# nginx держит сертификат в памяти — обычно его перезагружает systemd-таймер
# (cert-nginx-reload.timer) в течение 30 мин; принудительно сразу:
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload

# посмотреть текущие даты
docker exec app-certbot-1 openssl x509 \
  -in /etc/letsencrypt/live/5.188.31.27/fullchain.pem -noout -dates

# что видел/делал таймер
systemctl --user status cert-nginx-reload.timer cert-nginx-reload.service
journalctl --user -u cert-nginx-reload.service -n 50
```

### Мониторинг истечения

`php artisan cert:check` (ежедневно в 09:00 UTC через scheduler-loop супервизора) подключается к `nginx:443`, разбирает выдаваемый сертификат и:

- пишет `ERROR` в Telegram-канал при ≤ 3 днях до конца (совпадает с `renew_before_expiry = 3 days` certbot, так что алерт срабатывает только если ломается само продление; переопределяется `--min-days=N`) либо при просроченном/недостижимом сертификате;
- в остальных случаях печатает информационную строку.

Telegram-канал получает всё, что логируется на уровне `error`, через кастомный канал `telegram` (`app/Logging/TelegramHandler.php`, конфигурируется через `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` в `.env`).

## Переключатель регистрации пользователей

```bash
docker compose -f docker-compose.prod.yml exec app php artisan registration:disable
docker compose -f docker-compose.prod.yml exec app php artisan registration:enable
```

Флаг живёт в кэше (драйвер database → переживает рестарты). Пока отключена, `POST /api/auth/register` отвечает `403 {"message": "Регистрация временно приостановлена"}`.

## Разбор проблем (реальные инциденты)

### Сертификат истёк, браузер показывает `ERR_CERT_DATE_INVALID`

1. `docker logs --tail 40 app-certbot-1` — тут ошибка продления (на хосте лог-файла нет; certbot пишет в stdout).
2. Историческая первопричина: `standalone`-авторизатор никогда не мог продлиться (его сервер челленджей был недоступен; nginx отдавал Let's Encrypt SPA). Исправлено переходом на webroot — не откатывай.
3. После любого успешного продления: `nginx -s reload` (или рестарт контейнера nginx), иначе nginx продолжит отдавать старый сертификат из памяти.
4. Проверка снаружи: `curl -sI https://5.188.31.27/` (без `-k`) — не должен падать по TLS.

### Сборка падает с `checking context: no permission to read …` или молча даёт устаревший образ

Legacy-билдер (на VPS нет buildx) прерывается, когда в контексте сборки есть нечитаемые файлы — например, root-овые `letsencrypt/archive/*-privkey*.pem`, появившиеся после продления. `.dockerignore` в корне репо держит `docker/prod/letsencrypt` вне контекста; не удаляй эту строку. Он же исключает `.env`, `.git`, `vendor` и node_modules — `COPY . .` не должен запекать секреты или локальные зависимости в образы.

### `.env` изменился, но приложение игнорирует новые значения

PHP читает `.env` только при сборке config-кэша. Если внутри контейнера есть `bootstrap/cache/config.php`, измени `.env`, затем:

```bash
docker compose -f docker-compose.prod.yml exec app sh -c "rm -f bootstrap/cache/config.php && php artisan config:clear"
docker compose -f docker-compose.prod.yml restart app
```

Проверь через `php artisan config:show <channel>`, прежде чем считать, что изменение применилось.

### `cache:clear` включает регистрацию обратно

Флаг регистрации живёт в кэше. После любого сброса кэша повтори `registration:disable`.

## Заметки по безопасности

- Приватные ключи никогда не попадают в контекст сборки или образы (`docker/prod/letsencrypt` dockerignored). Если образ старше этого правила — вычисти его: `docker image prune -a -f`.
- После любой утечки ротируй токен бота через @BotFather; обнови `TELEGRAM_BOT_TOKEN` в `.env` и очисти config-кэш (см. выше).
- Порты Postgres/Redis не публикуются наружу; наружу торчит только nginx (80/443).