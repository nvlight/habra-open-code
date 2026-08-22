# Habra API

A backend-only JSON API clone of the core [habr.com](https://habr.com) entities — publications, hubs, companies, and social activity — built with **Laravel 13**.

## Documentation

| Document | Contents |
|---|---|
| [`docs/domain.md`](docs/domain.md) | Domain model: entities, ER diagram, enums, business rules |
| [`docs/api.md`](docs/api.md) | Full reference for every endpoint with examples |

## Stack

- PHP 8.3+, Laravel 13.17
- PostgreSQL (dev via Sail; prod v16)
- Laravel Sanctum — Bearer tokens for the API
- Pest 5 — tests; Larastan 3 (level 5) — static analysis; Pint — code style

## Entities

`User` · `Company` · `Industry` · `Hub` · `Publication` (article/post/news in one table) · `Tag` · `Comment` (nested tree) · `Vote` (morph: publications/comments/karma) · `Bookmark` · `Subscription` (morph: users/hubs/companies) · `Badge`

Relations and voting rules are described in [docs/domain.md](docs/domain.md).

## Quick Start

Requires Docker (Laravel Sail).

```bash
composer install                 # dependencies + vendor/bin/sail
sail up -d                       # containers (app, pgsql, redis…)
sail artisan key:generate        # app key (if not set yet)
sail artisan migrate:fresh --seed
```

The API is served at `http://localhost/api`. Smoke check:

```bash
curl http://localhost/api/publications?per_page=2
```

Demo admin account after seeding:

```
login:  admin
email:  admin@habr.test
password: password
```

All seeded users share the password `password`.

## Commands

```bash
sail artisan migrate:fresh --seed   # rebuild DB with demo data
sail bin pest                       # tests (49 feature tests)
sail bin pint --dirty               # code style
sail bin phpstan analyse            # static analysis (level 5)

composer dev                        # local server without Docker
```

## Key Directory Structure

```
app/
├── Enums/          # PublicationType/Status, Difficulty, Label, VoteSubject…
├── Http/
│   ├── Controllers/Api/   # 11 controllers (Auth, Publication, Vote, Feed…)
│   ├── Requests/          # validation (StorePublicationRequest…)
│   ├── Resources/         # API resources (PublicationResource…)
│   └── Policies/          # author permissions on publication/comment
├── Models/         # 11 models
└── Services/       # VoteService, PublicationQueryService
routes/api.php      # 38 endpoints
database/
├── factories/      # factories for all models (+states: published, sandbox…)
├── migrations/     # PostgreSQL schema
└── seeders/        # demo data: hubs, companies, posts, comments, votes
tests/Feature/      # Pest tests grouped by endpoint family
```
