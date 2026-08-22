# API Reference

Backend-клон Хабра на Laravel 13. Все запросы идут к префиксу `/api`, формат — JSON.

## Аутентификация

Sanctum Bearer-токены. Токен выдаётся при регистрации/логине и передаётся в заголовке:

```
Authorization: Bearer <token>
```

Логин работает **и по email, и по логину**. Эндпоинты с 🔒 требуют авторизации (иначе `401`).

## Пагинация

Все списки пагинируются Laravel'ом (`?page=2&per_page=20`, `per_page` ≤ 100):

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
| `403` | Действие запрещено (например, чужая публикация) |
| `404` | Объект не найден; draft-публикация для неавтора |
| `422` | Ошибка валидации: `{"message": "...", "errors": {"field": ["..."]}}` |

---

## Auth — `app/Http/Controllers/Api/AuthController.php`

### Регистрация

```http
POST /api/auth/register
```

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

`login` — email **или** логин. `200` → `{ user, token }`. Неверные данные → `401`.

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

## Publications

Единый ресурс для статей/постов/новостей. Идентификатор — числовой `id` (как нумерация `/ru/articles/1072300/` на Хабре).

### Список (публично)

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
| `min_rating` | целое ≥ рейтинга | без фильтра |
| `sort` | `new` (по дате) \| `best` (по рейтингу) | `new` |
| `status` | `published` \| `sandbox` | `published` |

Элемент списка (краткий ресурс, без `body`):

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

Песочница: `GET /api/publications?status=sandbox`.

### Просмотр (публично)

```http
GET /api/publications/{id}
```

→ то же + `body`, `source_url`, `created_at`, `updated_at`. Увеличивает `views_count`. Draft видит только автор.

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

- `title`, `body`, `type` — обязательны;
- `status` — только `draft` (по умолчанию) или `sandbox`;
- `hubs` — массив id хабов (≤ 5); `tags` — массив строк (≤ 10), теги создаются на лету;
- корпоративная публикация: `company_id` компании, где вы сотрудник.

`201` → полный ресурс.

### Обновление 🔒 (только автор)

```http
PUT|PATCH /api/publications/{id}
```

Те же поля, кроме `type` и `status`; повторная передача `hubs`/`tags` полностью пересинхронизирует их.

### Удаление 🔒 (только автор)

```http
DELETE /api/publications/{id}   →  { "message": "Публикация удалена" }
```

### Публикация 🔒 (только автор)

```http
POST /api/publications/{id}/publish
```

Переводит draft/sandbox в `published`, ставит `published_at`.

---

## Comments

Дерево комментариев: `parent_id` ссылается на комментарий той же публикации.

### Список (публично)

```http
GET /api/publications/{publication}/comments
```

Возвращает только корневые комментарии с вложенными `replies` (глубина 3 уровня):

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
        "parent_id": 101,
        "replies": []
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

`201` → созданный комментарий; увеличивает `comments_count` публикации. Ответ на чужой комментарий из другой публикации → `404`.

### Удаление 🔒 (только автор)

```http
DELETE /api/comments/{comment}   →  { "message": "Комментарий удалён" }
```

Пересчитывает `comments_count` публикации (ответы удаляются каскадно).

---

## Votes

Тело всех голосов: `{ "value": 1 }` или `{ "value": -1 }`. Повторное поведение описано в [domain.md](domain.md#голосование): тот же голос снимается, противоположный — переключается.

```http
POST /api/publications/{publication}/vote   →  { "rating": 14, "votes_up": 16, "votes_down": 2 }
POST /api/comments/{comment}/vote           →  { "rating": 6 }
POST /api/users/{user}/karma                →  { "karma": 1133 }
```

---

## Bookmarks

```http
POST   /api/publications/{publication}/bookmark   🔒  добавить
DELETE /api/publications/{publication}/bookmark   🔒  убрать
GET    /api/bookmarks                             🔒  мои закладки (список публикаций)
```

Повторное добавление не дублируется; `bookmarks_count` публикации обновляется автоматически.

---

## Subscriptions

Подписка на пользователя, хаб или компанию. `key` — числовой id **или** естественный ключ: логин / alias / slug.

```http
POST   /api/subscriptions/{type}/{key}     🔒  подписаться
DELETE /api/subscriptions/{type}/{key}     🔒  отписаться
GET    /api/subscriptions                  🔒  мои подписки по группам
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

## Feed — персональная лента 🔒

```http
GET /api/feed?types[]=article&difficulties[]=hard&min_rating=25&sort=new&per_page=20&global=1
```

- По умолчанию показывает опубликованное из **хабов и авторов**, на которые есть подписки;
- query-параметры перекрывают сохранённые `feed_settings`;
- `global=1` — игнорировать подписки (вся лента);
- сохранение настроек по умолчанию: `PUT /api/profile` с полем `feed_settings` (см. ниже).

## Profile 🔒

```http
PUT /api/profile
```

```json
{
  "name": "Иван Тестов",
  "about": "Backend-разработчик",
  "location": "Тбилиси, Грузия",
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
GET /api/users                          # авторы по рейтингу
GET /api/users/{login}                  # профиль: about, karma, badges, компания, счётчики
GET /api/users/{login}/publications     # его опубликованные статьи (?type=&sort=)
GET /api/users/{login}/comments         # его комментарии
GET /api/users/{login}/followers        # кто на него подписан
GET /api/users/{login}/following        # на кого он подписан (пользователи)
```

## Hubs

```http
GET /api/hubs                           # все хабы по рейтингу
GET /api/hubs/{alias}                   # карточка хаба
GET /api/hubs/{alias}/publications      # лента хаба (?type=&difficulty=&label=&min_rating=&sort=)
```

## Companies

```http
GET /api/companies                      # блоги компаний по рейтингу
GET /api/companies/{slug}               # карточка: описание, отрасли, представитель, счётчики
GET /api/companies/{slug}/publications  # корпоративные публикации
GET /api/companies/{slug}/employees     # сотрудники
```
