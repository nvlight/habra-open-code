# AGENTS.md

## Project Overview

This is a **Laravel 13** web application scaffold (fresh installation, no custom business logic yet).

## Tech Stack

- **Backend**: PHP 8.3+, Laravel 13.17
- **Frontend**: Vite 8, Tailwind CSS 4, Alpine.js (optional via Laravel)
- **Database**: PostgreSQL (dev: v18 via Sail, prod: v16), SQLite for testing
- **Queue/Cache**: Redis (production), database driver (local dev)
- **Auth**: Laravel Sanctum (API token + SPA stateful auth)
- **Testing**: Pest 5
- **Static Analysis**: Larastan 3 (level 5)
- **Code Style**: Laravel Pint 1

## Project Structure

```
app/
  Http/Controllers/    # Only base Controller.php (no custom controllers yet)
  Models/              # Only User.php model
  Providers/           # AppServiceProvider.php
bootstrap/
  app.php              # Middleware: guest redirect -> 401, JSON exceptions for API
config/                # Standard Laravel config files (including sanctum.php)
database/
  factories/           # UserFactory.php
  migrations/          # users, cache, jobs, personal_access_tokens
  seeders/             # DatabaseSeeder.php
docker/prod/
  Dockerfile           # Multi-stage PHP 8.5-FPM Alpine build
  nginx.conf           # API-only nginx + TLS (Let's Encrypt IP cert)
  supervisord.conf     # FPM + queue:work + scheduler
  php.ini              # OPcache 256MB, JIT 128MB
resources/
  css/app.css          # Tailwind CSS entry (v4 syntax: @import 'tailwindcss')
  js/app.js            # JS entry (empty)
  views/               # welcome.blade.php only
routes/
  web.php              # Single route: GET / -> welcome view
  api.php              # Single route: GET /api/user (auth:sanctum)
  console.php          # Single artisan command: inspire
tests/
  Pest.php             # Pest base config extending TestCase
  Feature/
    ExampleTest.php    # Returns a successful response (Pest syntax)
  Unit/
    ExampleTest.php    # Asserts that true is true (Pest syntax)
.dockerignore          # Excludes vendor, node_modules, .git, .env.*, etc.
.env.production        # Production env template (Redis for session/cache/queue)
docker-compose.prod.yml # nginx, app, postgres, redis, certbot
phpstan.neon.dist      # Larastan level 5, analyzes app/ directory
```

## Running the Project

```bash
# First-time setup (installs deps, copies .env, generates key, runs migrations)
composer setup

# Start development server (with Vite hot-reload)
composer dev
# OR
php artisan dev

# Run tests (Pest)
composer test
# OR
php artisan test

# Code style (Laravel Pint)
composer pint

# Static analysis (Larastan)
composer phpstan

# Production (Docker)
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml build app
```

## Development Conventions

- **PHP version**: 8.3+ (use attributes like `#[Fillable]`, `#[Hidden]` on models)
- **Indentation**: 4 spaces (PHP), 2 spaces (YAML), 4 spaces (Docker Compose)
- **Line endings**: LF
- **Final newline**: yes
- **Trailing whitespace**: trimmed (except .md files)

## Key Patterns

- Models use PHP 8 attributes (`#[Fillable]`, `#[Hidden]`) instead of `$fillable`/`$hidden` properties
- Database: PostgreSQL in both dev and production; SQLite for tests
- Sessions, cache, and queue use `redis` in production, `database` in local dev
- Sanctum is installed for API token authentication
- Bootstrap configures guest redirect to 401 (API-oriented) and JSON exception rendering for API routes
- Health check endpoint: `GET /up`

## Deployment

- **Production VPS**: Ubuntu 24.04, Docker + Docker Compose
- **SSL**: Let's Encrypt IP certificate (6-day validity, auto-renewed via certbot)
- **Services**: nginx (TLS termination), app (PHP-FPM + queue worker + scheduler), PostgreSQL 16, Redis 7, certbot
- **Environment**: `.env` mounted as read-only volume into the app container
- **CI/CD**: GitHub Actions — `ci.yml` (Pint + PHPStan + Pest), `deploy.yml` (SSH deploy on merge to main)

## Testing

- Pest 5 with `tests/Unit/` and `tests/Feature/` directories
- Base config in `tests/Pest.php` extends TestCase for Feature tests
- Run: `composer test` or `php artisan test`
- Static analysis: `composer phpstan` (Larastan level 5)
