# Доменная модель

Клон сущностей [habr.com](https://habr.com) в виде backend API на Laravel 13.

## Обзор сущностей

| Сущность | Таблица | Назначение |
|---|---|---|
| **User** | `users` | Автор. Логин (уникальный, идентификатор вместо email в URL), имя, «о себе», аватар, карма, рейтинг, локация, место работы, кто пригласил |
| **Company** | `companies` | Корпоративный блог. Slug, описание, рейтинг, подписчики, сайт, численность, дата основания |
| **Industry** | `industries` | Отрасль деятельности компании («Домены и хостинг», «Финтех»…) |
| **Hub** | `hubs` | Тематический хаб. Alias (уникальный), название, описание, рейтинг, счётчик подписчиков |
| **Publication** | `publications` | Единая сущность для статьи/поста/новости (`type`). Статус (`draft/sandbox/published`), сложность, метка, перевод, кэш-счётчики |
| **Tag** | `tags` | Свободные теги публикации. Создаются на лету через `firstOrCreate` по имени |
| **Comment** | `comments` | Комментарий к публикации. Дерево через `parent_id` (self-reference) |
| **Vote** | `votes` | Голос ±1 за публикацию, комментарий или карму пользователя (**morph** `voteable`) |
| **Bookmark** | `bookmarks` | Закладка пользователя на публикацию |
| **Subscription** | `subscriptions` | Подписка на пользователя, хаб или компанию (**morph** `subscribable`) |
| **Badge** | `badges` | Значок достижения («Легенда», «Старожил»…). Выдаётся через pivot `badge_user` |

## ER-диаграмма

```mermaid
erDiagram
    USER ||--o{ PUBLICATION : "author (user_id)"
    COMPANY |o--o{ PUBLICATION : "company_id"
    USER }o--o| COMPANY : "works at (company_id)"
    USER ||--o{ COMMENT : "author"
    PUBLICATION ||--o{ COMMENT : has
    COMMENT |o--o| COMMENT : "parent_id (replies)"
    PUBLICATION ||--|{ HUB_PUB : ""
    HUB ||--|{ HUB_PUB : ""
    PUBLICATION ||--|{ PUB_TAG : ""
    TAG ||--|{ PUB_TAG : ""
    COMPANY ||--|{ COMPANY_INDUSTRY : ""
    INDUSTRY ||--|{ COMPANY_INDUSTRY : ""
    COMPANY ||--|{ COMPANY_USER : "employees"
    USER ||--|{ COMPANY_USER : ""
    USER ||--|{ BADGE_USER : awarded
    BADGE ||--|{ BADGE_USER : ""
    USER ||--o{ VOTE : casts
    USER ||--o{ BOOKMARK : saves
    PUBLICATION ||--o{ BOOKMARK : saved-as
    USER ||--o{ SUBSCRIPTION : subscribes
    USER ||--o| USER : "invited_by"

    VOTE {
        string voteable_type "Publication|Comment|User"
        int voteable_id
        tinyint value "-1|+1"
    }
    SUBSCRIPTION {
        string subscribable_type "User|Hub|Company"
        int subscribable_id
    }
```

### Полиморфные связи (morph)

- **`votes.voteable`** → `Publication` / `Comment` / `User`.
  Голосование за публикацию и комментарий пересчитывает их рейтинг; голос за пользователя меняет его **карму**.
- **`subscriptions.subscribable`** → `User` / `Hub` / `Company`.
  Для Hub и Company ведётся денормализованный `subscribers_count`; у User число подписчиков считается запросом.

Уникальность: один пользователь — один голос на объект; одна подписка/закладка на объект.

## Enum'ы

Все перечисления живут в `app/Enums/` и кастятся в моделях:

| Enum | Значения | Русские лейблы |
|---|---|---|
| `PublicationType` | `article`, `post`, `news` | Статья, Пост, Новость |
| `PublicationStatus` | `draft`, `sandbox`, `published` | — |
| `Difficulty` | `easy`, `medium`, `hard` | Простой, Средний, Сложный |
| `PublicationLabel` | `tutorial`, `case`, `analytics`, `opinion`, `review`, `digest`, `retrospective`, `roadmap` | Туториал, Кейс, Аналитика, Мнение, Обзор, Дайджест, Ретроспектива, Роадмэп |
| `VoteSubject` | `publications`, `comments`, `users` | — (маршрутизация голосов) |
| `SubscribableType` | `user`, `hub`, `company` | — |

## Бизнес-правила

### Жизненный цикл публикации

```
POST /api/publications
   │  status=draft (по умолчанию) или status=sandbox
   ▼
┌───────┐   POST /api/publications/{id}/publish   ┌───────────┐
│ draft │ ───────────────────────────────────────►│ published │──► виден во всех лентах
└───────┘                                         └───────────┘
    ▲
    │ status=sandbox при создании
┌─────────┐
│ sandbox │  публично доступен через GET /api/publications?status=sandbox
└─────────┘
```

- **draft** — виден только автору (остальным `404`);
- **sandbox** — аналог «Песочницы» Хабра: публичен в отдельном списке, не попадает в общую ленту;
- **published** — публикуется с `published_at = now()`; повторный вызов `publish` дату не сбрасывает;
- редактировать/удалять может только автор (`PublicationPolicy`); `type` после создания не меняется.

### Голосование

Эндпоинты: `POST .../vote` для публикаций и комментариев, `POST /api/users/{id}/karma`. Тело: `{"value": 1}` или `{"value": -1}`.

Поведение при повторном запросе того же пользователя (`VoteService`):

| Ситуация | Действие | Изменение рейтинга цели |
|---|---|---|
| Голоса нет | создаётся | `±value` |
| Тот же голос | снимается | `-value` |
| Другой голос | переключается | `±2·value` |

- Публикация: пересчитываются `rating = votes_up − votes_down`, `votes_up`, `votes_down`;
- Комментарий: пересчитывается `rating` (сумма значений);
- Пользователь: `karma += delta`.

### Кэш-счётчики

Рейтинги, `comments_count`, `bookmarks_count`, `subscribers_count` хранятся денормализованно в таблицах и обновляются сервисами/контроллерами при изменениях. Это позволяет сортировать ленты по рейтингу без JOIN по голосам.

### Персональная лента (`feed_settings`)

Настройки хранятся JSON-полем у пользователя (`PUT /api/profile`):

```json
{
  "types": ["article", "post"],
  "difficulties": ["easy", "medium"],
  "min_rating": 10
}
```

Приоритет: query-параметры запроса перекрывают сохранённые настройки. Если у пользователя есть подписки на хабы или авторов, лента ограничивается ими (отключается параметром `global=1`).

### Приглашения

`users.invited_by` — self-referencing FK: цепочка «кто кого пригласил» (на Хабре регистрация была только по инвайтам).
