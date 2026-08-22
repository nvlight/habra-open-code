# AGENTS.md

## Project Overview

Backend-only JSON API clone of habr.com core entities (publications, hubs, companies, social activity) built with **Laravel 13**.

## Tech Stack

- **PHP**: 8.3+ / Laravel 13.17
- **Database**: PostgreSQL (dev via Sail, prod v16); tests run against pgsql `testing` database with `RefreshDatabase`
- **Auth**: Laravel Sanctum (Bearer tokens)
- **Testing**: Pest 5 (`tests/Feature`, RefreshDatabase enabled globally in `tests/Pest.php`)
- **Static analysis**: Larastan 3 level 5 — must stay at 0 errors
- **Code style**: Laravel Pint

## Domain Model

11 models (`app/Models/`): `User`, `Company`, `Industry`, `Hub`, `Publication` (single table for article/post/news), `Tag`, `Comment` (nested via `parent_id`), `Vote` (morph `voteable`: Publication|Comment|User-karma), `Bookmark`, `Subscription` (morph `subscribable`: User|Hub|Company), `Badge`.

Enums live in `app/Enums/` and are cast in models: `PublicationType`, `PublicationStatus` (draft/sandbox/published), `Difficulty`, `PublicationLabel`, `VoteSubject`, `SubscribableType`.

Full docs: `docs/domain.md` (ER diagram, business rules), `docs/api.md` (all endpoints).

## Running

All artisan/vendor commands go through **Sail** (no host PHP):

```bash
sail artisan migrate:fresh --seed   # rebuild DB with demo data
sail bin pest                       # tests
sail bin pint --dirty               # style fix
sail bin phpstan analyse            # static analysis
```

## Development Conventions

- PHP 8.3; 4-space indent, LF, final newline
- Models use PHP 8 attributes `#[Fillable([...])]` / `#[Hidden([...])]` instead of properties
- Every model has a `@property` docblock listing columns with proper types (enums, Carbon, bool) — required for Larastan level 5 attribute inference
- Relations use explicit return types (`HasMany`, `BelongsTo`, `MorphMany`…)
- Validation in FormRequests; responses through JsonResources (`app/Http/Resources/`)
- Authorization via Policies (`PublicationPolicy`, `CommentPolicy`) + base Controller uses `AuthorizesRequests`
- Business logic that touches counters lives in services (`app/Services/VoteService.php`, `PublicationQueryService.php`)
- Denormalized counters (`rating`, `comments_count`, `bookmarks_count`, `subscribers_count`) are recalculated on write, never trusted from client input

## Known Pitfalls

- **Pivot naming**: BelongsToMany without an explicit table infers alphabetically sorted names — `hub_publication`, not `publication_hub`. Keep migrations consistent.
- **Ordering**: `scopePublished()` already adds `orderByDesc(published_at)`. To re-sort by rating use `$query->reorder()->orderByDesc('rating')` — chained `orderBy` alone becomes secondary sort.
- **Stringable comparisons**: `$request->string('sort') === 'best'` is always false (object vs string). Cast first: `(string) $request->string('sort', 'new')`.
- **Tests & auth**: within one test method Sanctum's `RequestGuard` memoizes the user across requests. After revoking a token call `$this->app->make('auth')->forgetGuards()` before asserting 401.
- **Mass assignment**: counter columns are not fillable; use `forceFill()` when setting them in tests/seeds.
- Faker `unique()->word()` overflows fast in seeders — dedupe tags via `firstOrCreate(['name' => fake()->word()])`.

## Testing

- Feature tests per endpoint group in `tests/Feature/` (Auth, Publication, Comment, Vote, Bookmark, Subscription, Feed)
- Factories have states: `published()`, `sandbox()`, `draft()`, `news()`, `post()`, `translation()`, `corporate()`
- Run before committing: `sail bin pest && sail bin pint --dirty && sail bin phpstan analyse`
