# Справочник API

Бэкенд-клон Habr на Laravel 13. Все запросы идут с префиксом `/api`, формат — JSON.

## Аутентификация

Bearer-токены Sanctum. Токен выдаётся при регистрации/входе и передаётся в заголовке:

```
Authorization: Bearer <token>
```

Вход работает **и по email, и по логину**. Эндпоинты со значком 🔒 требуют аутентификации (иначе `401`).

## Пагинация

Все списки используют пагинацию Laravel (`?page=2&per_page=20`, `per_page` ≤ 100):

```json
{
  "data": [ /* элементы */ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 58 }
}
```

## Коды ошибок

| Код | Когда |
|---|---|
| `401` | Нет/просрочен токен или неверные учётные данные |
| `403` | Запрещено (например, чужая публикация) |
| `404` | Не найдено; черновик для всех, кроме автора |
| `422` | Ошибка валидации: `{"message": "...", "errors": {"field": ["..."]}}` |

---

## Auth — `app/Http/Controllers/Api/AuthController.php`

### Регистрация

```http
POST /api/auth/register
```

> Регистрацию можно отключить на уровне приложения (`php artisan registration:disable` / `registration:enable`). Пока отключена, эндпоинт отвечает `403` с `{ "message": "Регистрация временно приостановлена" }`.

```json
{
  "name": "Иван Тестов",
  "login": "ivan_test",
  "email": "ivan@test.dev",
  "password": "secret1234",
  "password_confirmation": "secret1234"
}
```

`201` → пользователь + токен:

```json
{
  "user": { "id": 25, "login": "ivan_test", "name": "Иван Тестов", "rating": "0" },
  "token": "12|XyZ..."
}
```

### Вход

```http
POST /api/auth/login
{ "login": "admin", "password": "password" }
```

`login` принимает email **или** логин. `200` → `{ user, token }`. Неверные учётные данные → `401`.

### Выход 🔒

```http
POST /api/auth/logout
```

Отзывает текущий токен → `{ "message": "Вы вышли из системы" }`.

### Текущий профиль 🔒

```http
GET /api/me
```

→ `UserResource` со счётчиками публикаций/комментариев/подписчиков.

---

## Админ: архив habr.com 🔒

Управление офлайн-архивом habr.com (список URL + сырой JSON). Доступ — только пользователям с `is_admin = true` (засеян `admin/password`); остальным `403`. Сам пайплайн (очередь, воркеры, scheduler, восстановление после перезагрузки) описан в [`docs/habr-archive_ru.md`](habr-archive_ru.md). Все эндпоинты сбрасывают сессию там, где указано, поэтому **`fetch` — это и есть способ возобновить остановленный краул**: он очищает флаг отмены и курсор диспетчера, затем заново ставит в очередь всё `pending`.

### Статистика архива

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

- `published_since` — строки, чей реальный `published_at` (kek `timePublished`) ≥ `since`; растёт по мере краула (`total` и `published_since` меряют разное);
- `is_crawling` — heartbeat `last_activity` свежее 5 минут; `cancel_requested` — был запрошен стоп.

### Список источников

```http
GET /api/admin/habr/urls?status=pending&type=news&since=2026-01-01&per_page=25&page=1
```

`status`: `pending` | `fetched` | `failed` | `excluded`; `type`: `article` | `news` | `post` | `special`. Стандартный конверт пагинации, каждый элемент — `{ id, source_id, url, type, status, published_at, lastmod, http_status, attempts, content_file }`.

### Сбор URL (разовая выгрузка) 🔒

```http
POST /api/admin/habr/discover
```

Начинает новую сессию (сбрасывает флаг отмены + курсор) и ставит в очередь `DiscoverHabrUrlsJob` для (пере)сбора URL из sitemap. `202` → `{ "message": "...", "queued": true }`.

### Загрузка контента (разовая) 🔒

```http
POST /api/admin/habr/fetch
{ "limit": 100000 }
```

Начинает новую сессию и ставит в очередь каскад диспетчера по оставшимся источникам `pending`. `limit` (≥ 1) опционален: при отсутствии каскад докачивает **весь** остаток `pending`; если задан — ограничивает генерацию батчей. Так как эндпоинт заново устанавливает сессию, он **возобновляет остановленный архив**. `202` → `{ "message": "...", "queued": true, "limit": null }`.

### Остановка 🔒

```http
POST /api/admin/habr/stop
```

Ставит флаг отмены и делает best-effort пурж очереди RabbitMQ (мгновенный стоп); текущие fetch-задачи дочитывают свою статью. Уже загруженные строки сохраняются, остальные остаются `pending` — ничего не теряется. `202` → `{ "message": "...", "cancel_requested": true }`.

---

## Публикации

Единый ресурс для статей/постов/новостей. Идентификатор — числовой `id` (зеркалит нумерацию habr.com `/ru/articles/1072300/`).

### Список (публичный)

```http
GET /api/publications?type=article&hub=programming&company=timeweb&author=SLY_G&difficulty=medium&label=tutorial&min_rating=10&sort=best&status=published&per_page=20&page=1
```

| Параметр | Значения | По умолчанию |
|---|---|---|
| `type` | `article` \| `post` \| `news` | все |
| `hub` | alias хаба (`python`) | все |
| `company` | slug компании (`timeweb`) | все |
| `author` | логин автора | все |
| `difficulty` | `easy` \| `medium` \| `hard` | все |
| `label` | метка (`tutorial`, `case`…) | все |
| `min_rating` | целое ≥ рейтинга | фильтра нет |
| `sort` | `new` (по дате) \| `best` (по рейтингу) | `new` |
| `status` | `published` \| `sandbox` | `published` |

Элемент списка (компактный ресурс, без `body`):

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

> Значения вида названий и меток — примерные данные из засеянного русскоязычного UI Habr.

Песочница: `GET /api/publications?status=sandbox`.

### Просмотр (публичный)

```http
GET /api/publications/{id}
```

→ то же плюс `body`, `source_url`, `created_at`, `updated_at`. Инкрементит `views_count`. Черновик виден только автору.

### Создание 🔒

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

- `title`, `body`, `type` — обязательные;
- `status` — только `draft` (по умолчанию) или `sandbox`;
- `hubs` — массив id хабов (≤ 5); `tags` — массив строк (≤ 10), теги создаются на лету;
- корпоративный пост: передай `company_id` компании, где ты сотрудник.

`201` → полный ресурс.

### Обновление 🔒 (только автор)

```http
PUT|PATCH /api/publications/{id}
```

Те же поля, кроме `type` и `status`; повторная отправка `hubs`/`tags` полностью пересинхронизирует их.

### Удаление 🔒 (только автор)

```http
DELETE /api/publications/{id}   →  { "message": "Публикация удалена" }
```

### Публикация 🔒 (только автор)

```http
POST /api/publications/{id}/publish
```

Переводит draft/sandbox в `published` и ставит `published_at`.

---

## Комментарии

Дерево: `parent_id` ссылается на комментарий той же публикации.

### Список (публичный)

```http
GET /api/publications/{publication}/comments
```

Возвращает только корневые комментарии с вложенными `replies` (3 уровня). Авторы присутствуют на каждом уровне вложенности:

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

### Создание 🔒

```http
POST /api/publications/{publication}/comments
{ "body": "Мой комментарий", "parent_id": null }
```

`201` → созданный комментарий с наполненными дефолтами (`rating` всегда `0`, никогда не `null`); инкрементит `comments_count` публикации. Ответ на комментарий другой публикации → `404`.

### Удаление 🔒 (только автор)

```http
DELETE /api/comments/{comment}   →  { "message": "Комментарий удалён" }
```

Пересчитывает `comments_count` публикации (реплаи удаляются каскадно).

---

## Голосование

Все голоса имеют тело `{ "value": 1 }` или `{ "value": -1 }`. Поведение при повторных голосах описано в [domain_ru.md](domain_ru.md#голосование): тот же голос ещё раз — снять голос, противоположный — переключить.

```http
POST /api/publications/{publication}/vote   →  { "rating": 14, "votes_up": 16, "votes_down": 2 }
POST /api/comments/{comment}/vote           →  { "rating": 6 }
POST /api/users/{user}/karma                →  { "karma": 1133 }
```

---

## Закладки

```http
POST   /api/publications/{publication}/bookmark   🔒  добавить
DELETE /api/publications/{publication}/bookmark   🔒  убрать
GET    /api/bookmarks                             🔒  мои закладки (список публикаций)
```

Повторное добавление не дублирует; `bookmarks_count` публикации обновляется автоматически.

---

## Подписки

Подписка на пользователя, хаб или компанию. `key` — числовой id **или** естественный ключ: login / alias / slug.

```http
POST   /api/subscriptions/{type}/{key}     🔒  подписаться
DELETE /api/subscriptions/{type}/{key}     🔒  отписаться
GET    /api/subscriptions                  🔒  мои подписки, сгруппированы по типам
```

`type`: `user` | `hub` | `company`. Примеры:

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" https://host/api/subscriptions/hub/python
curl -X POST -H "Authorization: Bearer $TOKEN" https://host/api/subscriptions/user/SLY_G
curl -X DELETE -H "Authorization: Bearer $TOKEN" https://host/api/subscriptions/company/timeweb
```

Ответ: `{ "message": "Подписка оформлена", "subscribed": true }`. Неизвестный ключ → `404`.

`GET /api/subscriptions`:

```json
{
  "users":     [ { "id": 4, "login": "SLY_G", "rating": "232.10" } ],
  "hubs":      [ { "id": 1, "alias": "programming", "subscribers_count": 8 } ],
  "companies": [ { "id": 1, "slug": "timeweb", "name": "Timeweb Cloud" } ]
}
```

---

## Лента — персональная 🔒

```http
GET /api/feed?types[]=article&difficulties[]=hard&min_rating=25&sort=new&per_page=20&global=1
```

- По умолчанию показывает опубликованные посты **из хабов и авторов, на которых вы подписаны**;
- query-параметры перекрывают сохранённые `feed_settings`;
- `global=1` — игнорировать подписки (вся лента);
- для сохранения настроек по умолчанию — `PUT /api/profile` с полем `feed_settings` (ниже).

## Профиль 🔒

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

## Пользователи

```http
GET /api/users                          # авторы, отсортированы по рейтингу
GET /api/users/{login}                  # профиль: about, karma, badges, company, счётчики
GET /api/users/{login}/publications     # их опубликованные посты (?type=&sort=)
GET /api/users/{login}/comments         # их комментарии
GET /api/users/{login}/followers        # кто подписан на них
GET /api/users/{login}/following        # на кого они подписаны (пользователи)
```

## Хабы

```http
GET /api/hubs                           # все хабы по рейтингу
GET /api/hubs/{alias}                   # карточка хаба
GET /api/hubs/{alias}/publications      # лента хаба (?type=&difficulty=&label=&min_rating=&sort=)
```

## Компании

```http
GET /api/companies                      # корпоративные блоги по рейтингу
GET /api/companies/{slug}               # карточка: описание, отрасли, представитель, счётчики
GET /api/companies/{slug}/publications  # корпоративные публикации
GET /api/companies/{slug}/employees     # сотрудники
```