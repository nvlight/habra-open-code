# AGENTS.md

## Project Overview

This is a **Laravel 13** web application scaffold (fresh installation, no custom business logic yet).

## Tech Stack

- **Backend**: PHP 8.3+, Laravel 13.17
- **Frontend**: Vite 8, Tailwind CSS 4, Alpine.js (optional via Laravel)
- **Database**: PostgreSQL (via Laravel Sail), SQLite for local/testing
- **Queue/Cache**: Database driver (default), Redis available
- **Message Queue**: RabbitMQ (configured in compose.yaml)
- **Auth**: Laravel Sanctum 4 (API token auth)
- **Testing**: PHPUnit 12

## Project Structure

```
app/
  Http/Controllers/    # Only base Controller.php (no custom controllers yet)
  Models/              # Only User.php model
  Providers/           # AppServiceProvider.php
config/                # Standard Laravel config files
database/
  factories/           # UserFactory.php
  migrations/          # users, cache, jobs, personal_access_tokens
  seeders/             # DatabaseSeeder.php
resources/
  css/app.css          # Tailwind CSS entry (v4 syntax: @import 'tailwindcss')
  js/app.js            # JS entry (empty)
  views/               # welcome.blade.php only
routes/
  web.php              # Single route: GET / → welcome view
  api.php              # Single route: GET /api/user (auth:sanctum)
  console.php          # Single artisan command: inspire
tests/
  Feature/             # ExampleTest.php
  Unit/                # (empty)
```

## Running the Project

```bash
# First-time setup (installs deps, copies .env, generates key, runs migrations, builds frontend)
composer setup

# Start development server (with Vite hot-reload)
composer dev
# OR
php artisan dev

# Run tests
composer test
# OR
php artisan test

# Using Docker (Laravel Sail)
./vendor/bin/sail up
./vendor/bin/sail artisan migrate
```

## Development Conventions

- **PHP version**: 8.3+ (use attributes like `#[Fillable]`, `#[Hidden]` on models)
- **Indentation**: 4 spaces (PHP), 2 spaces (YAML), 4 spaces (Docker Compose)
- **Line endings**: LF
- **Final newline**: yes
- **Trailing whitespace**: trimmed (except .md files)

## Key Patterns

- Models use PHP 8 attributes (`#[Fillable]`, `#[Hidden]`) instead of `$fillable`/`$hidden` properties
- Database: PostgreSQL in production (Sail), SQLite for local dev/testing
- Sessions, cache, and queue all use `database` driver by default
- Sanctum is installed for API authentication
- Vite is configured with Tailwind CSS v4 plugin and Instrument Sans font from Bunny CDN

## Testing

- PHPUnit 12 with `tests/Unit/` and `tests/Feature/` directories
- Test database: SQLite (in-memory via `phpunit.xml` env vars)
- Run: `composer test` or `php artisan test`
