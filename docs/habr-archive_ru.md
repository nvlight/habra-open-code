# Пайплайн архива habr.com

Офлайн-архив habr.com: полный список URL публикаций плюс сырой JSON каждой статьи, хранится локально. Работает через очередь (`rabbitmq`), поэтому для его работы нужны воркеры.

## Что он делает

- **Discover** — обходит sitemap habr (`https://habr.com/sitemap*`) и собирает каждый URL `/ru/`, чей `lastmod` ≥ `2026-01-01` (настраивается), пропуская дубликаты `source_id`. По одному URL на строку в таблице `habr_sources` с нормализованным типом: `article` / `news` / `post` / `special` (корпоративный контент сохраняет свой `company_slug`).
- **Fetch** — скачивает сырой JSON каждой статьи из внутреннего kek-API (`https://habr.com/kek/v2/publications/{id}?fl=*.en%2CrobotsTxt`) и сохраняет в `storage/app/habr/{source_id}.json`. Точная дата публикации (`timePublished`) пишется в `published_at`.
- **Count** — авторитетное число публикаций, опубликованных с даты: `habr:count --since=2026-01-01` (по реальному `published_at`, не по эвристикам URL).

Масштаб для ориентира: полный discover на 2026-01-01 дал **57 462** строки (`~/982 250` id источника ≈ граница 2026).

### Статусы источников

| Статус | Значение |
|---|---|
| `pending` | обнаружен, поставлен в очередь на загрузку |
| `fetched` | сохранён в `storage/app/habr/{id}.json` |
| `failed` | загрузка упала (исчерпан рейт-лимит, origin 403/5xx, …) |
| `excluded` | origin вернул `404` — статьи больше нет (в списке, статьи нет) |

## Команды

Запуск через Sail (на хосте нет PHP):

```bash
sail artisan habr:discover --since=YYYY-MM-DD   # (пере)собрать список URL из sitemap
sail artisan habr:fetch --limit=500             # загрузить N оставшихся источников (по умолчанию 500)
sail artisan habr:retry-failed                  # сбросить все failed → pending
sail artisan habr:count --since=YYYY-MM-DD      # посчитать источники с published_at ≥ даты
```

`habr:retry-failed` только сбрасывает статус; сама перезагрузка происходит при следующем диспатче (ежедневные 03:20/03:30 или ручной запуск).

## Очередь: как запустить

В dev нет supervisor — воркеры и scheduler запускаются как `docker compose exec -d`-процессы и **умирают при любом рестарте контейнеров** (деплои, перезагрузки, отключение света). Запуск:

```bash
# 4 параллельных воркера, потребляющие очередь "default" на RabbitMQ
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120
docker compose exec -d laravel.test php artisan queue:work rabbitmq --sleep=3 --timeout=120

# ежедневные задачи (03:00/03:20/03:30) — только один экземпляр
docker compose exec -d laravel.test php artisan schedule:work
```

Конфигурация подключения — в `.env`: `QUEUE_CONNECTION=rabbitmq`, `RABBITMQ_HOST=rabbitmq`, `RABBITMQ_USER=sail`, `RABBITMQ_PASSWORD=password`. Connection в `queue:work` передаётся **позиционно** (`rabbitmq`), опции `--connection` нет.

Проверки работоспособности:

```bash
docker compose exec laravel.test sh -c 'ps aux | grep -c "[q]ueue:work rabbitmq"'   # → 4
docker compose exec laravel.test sh -c 'ps aux | grep -c "[s]chedule:work"'         # → 1
# глубина очереди через management API (vhost "/", пользователь sail/password):
curl -s -u sail:password http://localhost:15672/api/queues/%2F/default | grep messages
```

### После перезагрузки / рестарта контейнера

1. Подними контейнеры: `docker compose up -d` (сеть Sail) — **воркеры и scheduler пропали**, запусти их заново, как выше.
2. RabbitMQ перезапустился → его очередь пуста; ~2000 сообщений в полёте исчезают, но ничего не теряется: строки остаются `pending` в PostgreSQL.
3. Возобнови архив из админки (кнопка **«Разовая загрузка контента»**) или:
   `curl -X POST http://localhost/api/admin/habr/fetch -H "Authorization: Bearer $TOKEN" -d '{"limit":100000}'`

## Ежедневное расписание

`routes/console.php` (dev — `schedule:work`; prod — тот же scheduler под supervisord):

| Время | Что |
|---|---|
| 03:00 | `DiscoverHabrUrlsJob` — подобрать новые URL из sitemap |
| 03:20 | `habr:retry-failed` — вернуть в очередь вчерашние ошибки |
| 03:30 | `DispatchHabrFetchJob` — поставить на загрузку все оставшиеся `pending` |

## Семантика стопа / возобновления

Из админки можно остановить работающий краул (или `POST /api/admin/habr/stop`). Остановка:

1. ставит флаг отмены (кэш-ключ `habr:crawl:cancel`);
2. делает best-effort пурж очереди RabbitMQ через management API (мгновенный стоп; пара учётных данных `sail/password`).

Работающие fetch'и дочитывают свою статью; каждая задача перед диспатчем/редиспатчем перепроверяет флаг, так что ничего нового не ставится. Уже `fetched` строки не трогаются, всё остальное остаётся `pending` — **стопом ничего не теряется**.

Возобновление — это ещё один `POST /api/admin/habr/fetch` (или кнопка в админке): `start()` очищает флаг отмены **и** курсор краула, затем каскад диспетчера заново ставит в очередь все `pending` с начала.

## Каскад диспетчера и курсор

`DispatchHabrFetchJob` подпитывает очередь батчами по **2000** `source_id` и редиспатчит себя после каждого батча, идя по множеству `pending` с помощью **курсора** (кэш-ключ `habr:crawl:cursor` = последний переданный id) вместо SQL-offset:

```php
HabrSource::query()->where('source_id', '>', $cursor)->pending()
    ->orderBy('source_id')->limit(2000)->pluck('source_id');
```

Предыдущая версия на offset падала на середине: она шла 0/2000/4000… по множеству, которое ужималось воркерами, offset мог укатиться за ставший меньше набор, и каскад решал, что «готово», оставив ~22 тыс. строк `pending`. Курсор терпим к ужимающимся `pending` и никогда не передаёт один id дважды.

## Рейт-лимиты и ошибки

- origin `429` → `HabrRateLimitedException`; задача редиспатчит себя с `delay(max(Retry-After, 5))` и экспоненциальным backoff (`tries = 3`, `backoff = [10, 30, 120]`). Источник остаётся `pending`.
- origin `404` → статус `excluded` (строка остаётся: она и есть свидетельство существования URL).
- всё остальное (origin `403`/`5xx`, сеть, ошибка JSON) → исключение → задача в `failed_jobs`, статус `failed`; лечится `habr:retry-failed` или ежедневным 03:20.

При устойчивой нагрузке (десятки тысяч запросов за ~2 ч) habr начинает отдавать `403` на часть запросов — они уходят в `failed` и добираются ежедневным ретраем; на итоговый архив это не влияет.

## Кэш-ключи

Состояние краула живёт в кэше Laravel (в dev — Redis): `habr:crawl:cancel`, `habr:crawl:cursor`, `habr:crawl:queued_at`, `habr:crawl:last_activity`. `cache:clear` их стирает — свежий `POST /fetch` заново установит сессию, но активные воркеры всё равно пишут heartbeat.

## Админ-API

Эндпоинты и форматы ответов описаны в [`api_ru.md`](api_ru.md#админ-архив-habrcom).