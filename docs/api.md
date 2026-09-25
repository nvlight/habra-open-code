# API Reference

A backend clone of Habr built on Laravel 13. All requests go to the `/api` prefix; the format is JSON.

## Authentication

Sanctum bearer tokens. A token is issued on registration/login and sent in the header:

```
Authorization: Bearer <token>
```

Login works with **either email or login**. Endpoints marked with 🔒 require authentication (otherwise `401`).

## Pagination

All lists use Laravel pagination (`?page=2&per_page=20`, `per_page` ≤ 100):

```json
{
  "data": [ /* items */ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 58 }
}
```

## Error Codes

| Code | When |
|---|---|
| `401` | Missing/expired token or invalid credentials |
| `403` | Forbidden (e.g. someone else's publication) |
| `404` | Not found; a draft publication for anyone but its author |
| `422` | Validation error: `{"message": "...", "errors": {"field": ["..."]}}` |

---

## Auth — `app/Http/Controllers/Api/AuthController.php`

### Register

```http
POST /api/auth/register
```

> Registration can be disabled at the application level (`php artisan registration:disable` / `registration:enable`). While disabled, this endpoint responds `403` with `{ "message": "Регистрация временно приостановлена" }`.

```json
{
  "name": "Ivan Testov",
  "login": "ivan_test",
  "email": "ivan@test.dev",
  "password": "secret1234",
  "password_confirmation": "secret1234"
}
```

`201` → user + token:

```json
{
  "user": { "id": 25, "login": "ivan_test", "name": "Ivan Testov", "rating": "0" },
  "token": "12|XyZ..."
}
```

### Login

```http
POST /api/auth/login
{ "login": "admin", "password": "password" }
```

`login` accepts an email **or** a login name. `200` → `{ user, token }`. Invalid credentials → `401`.

### Logout 🔒

```http
POST /api/auth/logout
```

Revokes the current token → `{ "message": "Вы вышли из системы" }`.

### Current profile 🔒

```http
GET /api/me
```

→ `UserResource` with publication/comment/follower counters.

---

## Admin: habr.com archive 🔒

Management of the offline habr.com archive (URL list + raw JSON). Only users with `is_admin = true` (seeded `admin/password`); anyone else gets `403`. The pipeline itself (queue, workers, scheduler, reboot recovery) is described in [`docs/habr-archive.md`](habr-archive.md). All endpoints reset the session where noted, so **`fetch` is also the way to resume a stopped run** — it clears the cancel flag and the dispatch cursor, then re-enqueues everything still `pending`.

### Archive stats

```http
GET /api/admin/habr/stats?since=2026-01-01
```

```json
{
  "total": 57462,
  "since": "2026-01-01",
  "published_since": 41207,
  "pending": 11740,
  "fetched": 42040,
  "failed": 240,
  "excluded": 3442,
  "by_status": { "fetched": 42040, "pending": 11740, "failed": 240, "excluded": 3442 },
  "by_type": { "article": 32368, "news": 20469, "post": 4561, "special": 64 },
  "is_crawling": true,
  "cancel_requested": false,
  "queued_at": "2026-09-25T13:27:57+00:00",
  "last_activity": "2026-09-25T13:28:01+00:00"
}
```

- `published_since` — rows whose real `published_at` (kek `timePublished`) ≥ `since`; grows while the crawl is in progress (those `total`/`published_since` measure different things);
- `is_crawling` — heartbeat `last_activity` fresher than 5 min; `cancel_requested` — stop was requested.

### Source list

```http
GET /api/admin/habr/urls?status=pending&type=news&since=2026-01-01&per_page=25&page=1
```

`status`: `pending` | `fetched` | `failed` | `excluded`; `type`: `article` | `news` | `post` | `special`. Standard pagination envelope, each item is `{ id, source_id, url, type, status, published_at, lastmod, http_status, attempts, content_file }`.

### Discover (one-off URL collection) 🔒

```http
POST /api/admin/habr/discover
```

Starts a new session (clears cancel flag + cursor) and enqueues `DiscoverHabrUrlsJob` to (re)collect URLs from the sitemap. `202` → `{ "message": "...", "queued": true }`.

### Fetch (one-off content download) 🔒

```http
POST /api/admin/habr/fetch
{ "limit": 100000 }
```

Starts a new session and enqueues the dispatch cascade over the remaining `pending` sources. `limit` (≥ 1) is optional: when omitted the cascade drains **everything** still pending; when given, it caps the batch generation. Because it re-establishes the session, this endpoint **resumes a stopped archive**. `202` → `{ "message": "...", "queued": true, "limit": null }`.

### Stop 🔒

```http
POST /api/admin/habr/stop
```

Sets the cancel flag and best-effort purges the RabbitMQ queue (instant stop); in-flight fetches finish their current article. Already-fetched rows are kept, the rest stay `pending` — nothing is lost. `202` → `{ "message": "...", "cancel_requested": true }`.

---

## Publications

A single resource for articles/posts/news. Identifier is a numeric `id` (mirroring habr.com's `/ru/articles/1072300/` numbering).

### List (public)

```http
GET /api/publications?type=article&hub=programming&company=timeweb&author=SLY_G&difficulty=medium&label=tutorial&min_rating=10&sort=best&status=published&per_page=20&page=1
```

| Parameter | Values | Default |
|---|---|---|
| `type` | `article` \| `post` \| `news` | all |
| `hub` | hub alias (`python`) | all |
| `company` | company slug (`timeweb`) | all |
| `author` | author login | all |
| `difficulty` | `easy` \| `medium` \| `hard` | all |
| `label` | label (`tutorial`, `case`…) | all |
| `min_rating` | integer ≥ rating | no filter |
| `sort` | `new` (by date) \| `best` (by rating) | `new` |
| `status` | `published` \| `sandbox` | `published` |

List item (compact resource, without `body`):

```json
{
  "id": 61,
  "type": "article",
  "type_label": "Статья",
  "status": "published",
  "title": "Почему O(1) проигрывает O(n)",
  "lead": "Объясню структуры данных через очередь в поликлинике…",
  "cover": null,
  "difficulty": "medium",
  "difficulty_label": "Средний",
  "label": "analytics",
  "label_label": "Аналитика",
  "is_translation": false,
  "original_author": null,
  "is_recovery_mode": false,
  "reading_time": 11,
  "views_count": 10234,
  "reach": 10000,
  "rating": 13,
  "votes_up": 15,
  "votes_down": 2,
  "comments_count": 10,
  "bookmarks_count": 17,
  "published_at": "2026-08-21T09:00:00+00:00",
  "author": { "id": 5, "login": "dixmod", "name": "…", "avatar": null, "rating": "421.00" },
  "company": null,
  "hubs": [ { "id": 1, "alias": "programming", "name": "Программирование" } ],
  "tags": [ { "id": 7, "name": "go" }, { "id": 9, "name": "algorithms" } ]
}
```

> Field values such as titles and labels are sample data seeded from the Russian-language Habr UI.

Sandbox listing: `GET /api/publications?status=sandbox`.

### Show (public)

```http
GET /api/publications/{id}
```

→ same as above plus `body`, `source_url`, `created_at`, `updated_at`. Increments `views_count`. A draft is visible only to its author.

### Create 🔒

```http
POST /api/publications
```

```json
{
  "title": "Тестовая статья про Laravel",
  "lead": "Краткое описание",
  "body": "# Заголовок\nТекст статьи (markdown/html)",
  "type": "article",
  "status": "sandbox",
  "difficulty": "medium",
  "label": "tutorial",
  "is_translation": false,
  "source_url": null,
  "original_author": null,
  "company_id": null,
  "hubs": [1, 3],
  "tags": ["laravel", "php"]
}
```

- `title`, `body`, `type` — required;
- `status` — only `draft` (default) or `sandbox`;
- `hubs` — array of hub ids (≤ 5); `tags` — array of strings (≤ 10), tags are created on the fly;
- corporate post: pass the `company_id` of a company you are an employee of.

`201` → full resource.

### Update 🔒 (author only)

```http
PUT|PATCH /api/publications/{id}
```

Same fields except `type` and `status`; re-sending `hubs`/`tags` fully re-syncs them.

### Delete 🔒 (author only)

```http
DELETE /api/publications/{id}   →  { "message": "Публикация удалена" }
```

### Publish 🔒 (author only)

```http
POST /api/publications/{id}/publish
```

Moves draft/sandbox to `published` and sets `published_at`.

---

## Comments

Comment tree: `parent_id` references a comment of the same publication.

### List (public)

```http
GET /api/publications/{publication}/comments
```

Returns only top-level comments with nested `replies` (3 levels deep). Authors are present at every nesting level:

```json
[
  {
    "id": 101,
    "body": "Отличная статья!",
    "rating": 5,
    "parent_id": null,
    "publication_id": 61,
    "author": { "id": 3, "login": "fixin", "name": "…" },
    "replies": [
      {
        "id": 102,
        "body": "Согласен",
        "rating": 1,
        "parent_id": 101,
        "publication_id": 61,
        "author": { "id": 8, "login": "vasya", "name": "…" },
        "replies": [],
        "created_at": "2026-08-21T12:05:00+00:00"
      }
    ],
    "created_at": "2026-08-21T12:00:00+00:00"
  }
]
```

### Create 🔒

```http
POST /api/publications/{publication}/comments
{ "body": "Мой комментарий", "parent_id": null }
```

`201` → created comment with hydrated defaults (`rating` is always `0`, never `null`); increments the publication's `comments_count`. Replying to a comment that belongs to another publication → `404`.

### Delete 🔒 (author only)

```http
DELETE /api/comments/{comment}   →  { "message": "Комментарий удалён" }
```

Recalculates the publication's `comments_count` (replies are cascade-deleted).

---

## Votes

All votes share the body `{ "value": 1 }` or `{ "value": -1 }`. Repeated voting behavior is described in [domain.md](domain.md#voting): voting the same value again removes the vote, the opposite value switches it.

```http
POST /api/publications/{publication}/vote   →  { "rating": 14, "votes_up": 16, "votes_down": 2 }
POST /api/comments/{comment}/vote           →  { "rating": 6 }
POST /api/users/{user}/karma                →  { "karma": 1133 }
```

---

## Bookmarks

```http
POST   /api/publications/{publication}/bookmark   🔒  add
DELETE /api/publications/{publication}/bookmark   🔒  remove
GET    /api/bookmarks                             🔒  my bookmarks (list of publications)
```

Adding twice never duplicates; the publication's `bookmarks_count` updates automatically.

---

## Subscriptions

Subscribe to a user, hub, or company. `key` is either a numeric id **or** the natural key: login / alias / slug.

```http
POST   /api/subscriptions/{type}/{key}     🔒  subscribe
DELETE /api/subscriptions/{type}/{key}     🔒  unsubscribe
GET    /api/subscriptions                  🔒  my subscriptions grouped by type
```

`type`: `user` | `hub` | `company`. Examples:

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" https://host/api/subscriptions/hub/python
curl -X POST -H "Authorization: Bearer $TOKEN" https://host/api/subscriptions/user/SLY_G
curl -X DELETE -H "Authorization: Bearer $TOKEN" https://host/api/subscriptions/company/timeweb
```

Response: `{ "message": "Подписка оформлена", "subscribed": true }`. Unknown key → `404`.

`GET /api/subscriptions`:

```json
{
  "users":     [ { "id": 4, "login": "SLY_G", "rating": "232.10" } ],
  "hubs":      [ { "id": 1, "alias": "programming", "subscribers_count": 8 } ],
  "companies": [ { "id": 1, "slug": "timeweb", "name": "Timeweb Cloud" } ]
}
```

---

## Feed — personal feed 🔒

```http
GET /api/feed?types[]=article&difficulties[]=hard&min_rating=25&sort=new&per_page=20&global=1
```

- By default shows published posts **from hubs and authors you subscribe to**;
- query parameters override stored `feed_settings`;
- `global=1` — ignore subscriptions (the entire feed);
- to persist default settings use `PUT /api/profile` with the `feed_settings` field (see below).

## Profile 🔒

```http
PUT /api/profile
```

```json
{
  "name": "Иван Тестов",
  "about": "Backend developer",
  "location": "Tbilisi, Georgia",
  "avatar": "https://example.com/a.png",
  "feed_settings": {
    "types": ["article"],
    "difficulties": ["medium"],
    "min_rating": 10
  }
}
```

---

## Users

```http
GET /api/users                          # authors sorted by rating
GET /api/users/{login}                  # profile: about, karma, badges, company, counters
GET /api/users/{login}/publications     # their published posts (?type=&sort=)
GET /api/users/{login}/comments         # their comments
GET /api/users/{login}/followers        # who follows them
GET /api/users/{login}/following        # who they follow (users)
```

## Hubs

```http
GET /api/hubs                           # all hubs by rating
GET /api/hubs/{alias}                   # hub card
GET /api/hubs/{alias}/publications      # hub feed (?type=&difficulty=&label=&min_rating=&sort=)
```

## Companies

```http
GET /api/companies                      # corporate blogs by rating
GET /api/companies/{slug}               # card: description, industries, representative, counters
GET /api/companies/{slug}/publications  # corporate publications
GET /api/companies/{slug}/employees     # employees
```
