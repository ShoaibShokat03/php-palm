# PHP Palm – Project Memo

## Snapshot
- **Purpose:** lightweight, module-friendly PHP API framework with batteries-included routing, security, and ActiveRecord-style data access.
- **Entrypoint:** all HTTP traffic is funneled through `index.php`, which boots Composer autoloading, loads `.env`, enforces security middleware, and dispatches either frontend routes or API routes.
- **Tech stack:** PHP 7.4+, Composer packages `php-palm/php-palm` (core runtime) and `vlucas/phpdotenv` (env loader). MySQL connectivity lives in `App\Database\Db`.
- **Frontend SPA runtime:** `State()` and `Action()` helpers (see `app/Palm/*`) let view authors keep everything in PHP. The Palm compiler builds a virtual DOM payload + JS handlers, so buttons like `<button onclick="add">Count <?= $count ?></button>` stay in sync automatically. No manual attributes or JavaScript needed; POST forms still piggyback on the same runtime unless `data-spa-form="false"`.

## Request Lifecycle
1. **Static asset short-circuit:** GET requests are first checked against `public/` through `App\Core\PublicFileServer` so `/images/logo.png` works without `/public`.
2. **Environment + CORS:** `Dotenv::createImmutable('config/')` loads secrets, then `config/cors.php` drives `Access-Control-*` headers.
3. **Security middleware:** `index.php` adds hardened headers, blocks dangerous verbs, sanitizes `$_GET`/`$_POST`, enforces rate limiting using JSON counters in `app/storage/ratelimit`, and rejects spoofed proxy headers.
4. **Routing bootstrap:** `PhpPalm\Core\Route::init()` registers simple routes (`routes/api.php`) first, then every module discovered by `App\Core\ModuleLoader`. Each route remembers its source for conflict detection.
5. **Conflict detection:** `App\Core\RouteConflictChecker` inspects `Router::getRoutes()` and fails fast if the same method+path exists in both `api.php` and any `module:*` source.
6. **Dispatch:** `Route::dispatch()` hands control to `PhpPalm\Core\Router`, which trims the `/api` prefix, regex-matches dynamic params, and executes handlers with guarded exception handling. Responses are normalized to JSON arrays in controllers (`App\Core\Controller`).

## Routing Options
- **Simple routing (`routes/api.php`):** Best for prototypes. Directly register closures using `Route::{get|post|...}`. Example health endpoint plus helper-generated asset URLs already exist.
- **Modular routing (`modules/*`):** Production pattern mirroring NestJS/Laravel modules. Each module extends `App\Core\Module`, declares a name and route prefix, and wires CRUD routes via `Route`. Modules are auto-loaded; no manual includes required.

## Module Anatomy
- `Module.php`: extends the base `Module`, sets prefix (e.g., `/users`), and registers CRUD endpoints that point to a controller.
- `Controller.php`: extends `App\Core\Controller` to access `success()`, `error()`, and `getRequestData()`. Controllers should stay thin, delegating logic to services.
- `Service.php`: extends `App\Core\Service` (utility base) and hosts validation plus business rules. Calls into the module’s `Model` for persistence, returning structured arrays (`success`, `data`, `errors`).
- `Model.php`: extends `App\Core\Model`, sets `$table`, and inherits ActiveRecord-style helpers (`all`, `where`, `create`, relationships, eager loading). The Users demo module showcases the full stack.

## HTTP Helpers
- `PhpPalm\Core\Request` wraps the superglobals with cached headers/body parsing, JSON helpers, typed accessors (`boolean`, `integer`, `float`, `string`), `only/except`, file uploads, bearer token extraction, and AJAX detection.
- `App\Core\UrlHelper` builds absolute or relative URLs and piggybacks on `PublicFileServer` for full static asset links.

## Data Layer
- `App\Core\Model` pairs with `App\Core\QueryBuilder` to offer method-chained queries, relational helpers (`hasOne`, `hasMany`, `belongsTo`), eager loading (`with()`), caching, and attribute/property sync.
- `App\Database\Db` provides a thin mysqli wrapper that reads credentials from `.env` and exposes escaping plus insert IDs.
- `ACTIVERECORD_USAGE.md` documents the entire API (filters, pagination, search, relationships, serialization) and should be the go-to reference for contributors.

## Tooling & Automation
- `palm.bat` is the umbrella CLI. Subcommands proxy to `app/scripts/*.php` generators:
  - `palm make module <Name> [/route]` scaffolds Module + Controller + Service + Model with CRUD endpoints.
  - `palm make controller|service|model ...` add single components.
  - `palm make usetable all` introspects the database and auto-generates modules per table.
- `serve.bat` runs the PHP built-in server on port 8000 with friendly IP hints. Equivalent manual command: `php -S localhost:8000 index.php`.

## Configuration Notes
- `.env` resides under `config/` (not root) and is loaded automatically; template keys cover DB credentials plus optional API metadata.
- CORS defaults allow all origins but supports whitelisting. Update `config/cors.php` for production.
- Public files live under `public/` but are addressable at the URL root thanks to the custom file server.

## Current State & Next Steps
- Demo `Users` module provides end-to-end CRUD wiring and can be copied when adding real features.
- `routes/api.php` still contains example endpoints; remove duplicates after promoting a module to production to avoid conflict warnings.
- Ensure MySQL credentials are configured before calling ActiveRecord helpers—`Db` fails fast with descriptive errors.
- Recommended workflow: `composer install` → create `config/.env` → `palm serve` → build new APIs with `palm make ...` and refine Services for validation/business logic.

Use this memo as a quick orientation guide before diving deeper into the README or generator docs.

