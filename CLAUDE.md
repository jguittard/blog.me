# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A [Mezzio](https://docs.mezzio.dev/mezzio/) (Laminas) PSR-15 middleware application,
scaffolded from `mezzio/mezzio-skeleton`. Runtime stack: laminas-servicemanager
(DI), mezzio-fastroute (routing), mezzio-laminasviewrenderer (templates),
laminas-diactoros (PSR-7). PHP 8.5.

The app has a blog model — User / Post / Category / Tag with a php-db persistence
layer, `laminas-hydrator` mapping, and `Sql\Ddl` migrations (see below) — and a
server-rendered reader frontend: `GET /` (paginated post list), `GET /posts/{slug}`,
`GET /categories`, `GET /categories/{slug}`, plus `GET /api/ping`. The `.phtml`
templates use Tailwind via the Play CDN (`cdn.tailwindcss.com`), styled to the
shadcn/ui look (`bg-gray-50` page, white cards, `#2563eb` primary, Inter).

## Commands

```bash
composer serve              # run dev server at http://localhost:8080 (php -S, docroot public/)
composer test               # phpunit
composer test-coverage      # phpunit with clover.xml coverage
composer cs-check           # phpcs (Laminas Coding Standard)
composer cs-fix             # phpcbf autofix
composer static-analysis    # psalm (errorLevel 1, baselined in psalm-baseline.xml)
composer check              # cs-check + test
composer clear-config-cache # delete merged config cache after changing config
```

Run a single test:

```bash
vendor/bin/phpunit --filter PingHandlerTest
vendor/bin/phpunit test/AppTest/Handler/PingHandlerTest.php
```

Development mode (disables config cache, enables `debug`, loads
`config/autoload/*.local.php`):

```bash
composer development-enable   # also clears config cache
composer development-disable
composer development-status
```

When adding a Psalm-flagged issue that is pre-existing elsewhere, regenerate the
baseline with `composer static-analysis-update-baseline` rather than suppressing
inline.

`phpcs.xml.dist` / `psalm.xml.dist` disable a few sniffs/issues for
`src/App/src/Domain/*` — the pinned PHP_CodeSniffer (`^3.10`) and Psalm 6.13
mis-parse PHP 8.4 property hooks and the 8.5 `clone(...)` with-expression. Each
carries a comment; drop them once the tools catch up. New domain code should
otherwise pass both cleanly.

## Dependencies

Do not install Composer packages that are marked deprecated or abandoned
(`composer` prints `Package X is abandoned` on require/update, and Packagist
flags them). If a requested package is abandoned, stop and surface its suggested
replacement instead of installing it.

- **Database:** `php-db/phpdb` + `php-db/phpdb-mysql` (the `laminas/laminas-db`
  successor). The MySQL driver only exists on the unreleased 0.6 line, so both
  are pinned to their dev branches (`0.6.x-dev@dev` / `^0.4@dev`) — inline `@dev`
  flags, no global `minimum-stability` change. Re-pin to stable tags once
  released. Adapter config: `config/autoload/database.global.php` (reads
  `DATABASE_URL`); get it from the container via
  `$container->get(PhpDb\Adapter\AdapterInterface::class)`.
- **PHP 8.5 / platform reqs:** `mezzio/mezzio-tooling` caps its constraint at
  `~8.4.1`, so it's installed with `--ignore-platform-req=php+` and the PHP image
  sets `COMPOSER_IGNORE_PLATFORM_REQ=php+` (upper-bound only) so `composer
  install` works. Keeping tooling also pins `mezzio/mezzio-router` to 3.x.
  Drop the override once tooling supports 8.5 and re-run `composer update`.

## Local environment (Docker)

Full stack in `docker-compose.yml`: Caddy (TLS via mkcert) → nginx → php-fpm 8.5,
plus MariaDB 11.4, Redis, MinIO (uploads), Mailpit. Details in `docker/README.md`.

```bash
make certs && cp .env.example .env   # once (mkcert + hosts entries, see `make hosts`)
make build && make up && make install
```

App at <https://blog.me:8443> (`HTTPS_PORT` defaults to 8443 to dodge a local
proxy on 443; set it to 443 for bare `https://blog.me`). Run project commands
inside the container:
`docker compose exec -u www-data php composer test` (or `make test` / `make sh`).
Service credentials reach the app as env vars (`DATABASE_URL`, `REDIS_URL`,
`S3_*`, `MAILER_DSN`) — consume them from `config/autoload/*.local.php`.

## Architecture

Request flow: `public/index.php` → `config/container.php` (builds the
`ServiceManager` from merged config) → `Mezzio\Application` runs the pipeline in
`config/pipeline.php` → routing matches a route from `config/routes.php` →
dispatch invokes a `RequestHandlerInterface`.

- **`config/pipeline.php`** — the middleware pipeline (error handler → routing →
  implicit HEAD/OPTIONS → dispatch → not-found). Add cross-cutting middleware
  (auth, CORS, body parsing) here.
- **`config/routes.php`** — route table. Register with `$app->get/post/...($path,
  HandlerClass::class, 'route.name')`.
- **`src/App/src/ConfigProvider.php`** — the module's DI + template wiring. Every
  new handler is registered here: stateless ones under `invokables`, ones with
  constructor dependencies under `factories` — the blog handlers use
  `Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory` so no
  `*Factory` class is needed. Template namespace → path mappings live in
  `getTemplates()`.
- **Handlers** (`src/App/src/Handler/`) — one class per endpoint implementing
  `Psr\Http\Server\RequestHandlerInterface::handle()`, returning a Diactoros
  `HtmlResponse` / `JsonResponse`. `PostListHandler` / `PostViewHandler` /
  `CategoryListHandler` / `CategoryPostsHandler` pull a read repository +
  `TemplateRendererInterface`; the two listing handlers share `Handler\Pagination`
  (fetch `perPage + 1`, `hasNext()` detects the next page). A matched route with
  no data returns `HtmlResponse(render('error::404'), 404)` from the handler.
- **Templates** (`src/App/templates/`) — `.phtml` for laminas-view, referenced as
  `namespace::name` (e.g. `app::post-list`, `app::partial/post-card`).
  `layout::default` loads Tailwind (Play CDN) + Inter and wraps pages. Escape all
  dynamic output (`$this->escapeHtml*`); phpcs/psalm do not lint `.phtml`.

### Blog model (`src/App/src/Domain` + `src/App/src/Infrastructure`)

Domain / Infrastructure split inside the `App` module:

- **`Domain/Entity/`** — `final readonly` entities (`User`, `Post`, `Category`,
  `Tag`). Immutable: mutations return a new instance via PHP 8.5
  `clone($this, [...])`; derived values are methods (`Post::isPublished()`,
  `readingTimeMinutes()`). Every `$id` is a **CUID2** `string`, generated in the
  factory (`Post::draft()`, `User::register()`, …), so it is set from creation —
  there is no auto-increment and no `withId()`. Timestamps are truncated to
  seconds (`->setMicrosecond(0)`) to round-trip through MySQL `DATETIME`.
- **`Domain/Value/`** — `Cuid::generate()` (CUID2, 24 chars, `CHAR(24)` columns);
  `PostStatus` backed enum; `Slug` / `Email` `final readonly` value objects with
  validating `fromString()` factories.
- **`Domain/ReadModel/`** — `PostListItem` / `PostView`: flat, hydrate-only DTOs
  for JOIN queries. Not `readonly` (PHP forbids hooks on readonly) — immutability
  via `public private(set)`; derived fields (`href`, `isNew`, `summary`,
  `readingTimeMinutes`) are virtual **property hooks**.
- **`Domain/Repository/`** — interfaces only. `*RepositoryInterface` (write side,
  entities) + `PostReadRepositoryInterface` (read side, DTOs).
- **`Infrastructure/Persistence/Hydrator/HydratorRegistry`** — builds configured
  `Laminas\Hydrator\ReflectionHydrator`s (Underscore naming + BackedEnum /
  DateTimeImmutable / custom `CsvListStrategy` + `ValueObjectStrategy`).
  `ReflectionHydrator` populates `readonly` / `private(set)` props on a
  constructor-less prototype — the shape `PhpDb\ResultSet\HydratingResultSet`
  needs.
- **`Infrastructure/Persistence/PhpDb/`** — repository implementations, bound to
  their interfaces in `PersistenceConfigProvider` (registered in
  `config/config.php`). Entity CRUD via `PhpDb\TableGateway`; read models via
  hand-built `PhpDb\Sql\Select` + `HydratingResultSet`. `save()` is an upsert
  (`HydratesRowsTrait::upsert()` probes for the row by CUID, then INSERT or
  UPDATE — no auto-increment to read back); `PhpDbPostRepository::save()` wraps
  it plus `post_tag` sync in a transaction.
- **`Infrastructure/Persistence/Migration/`** — `MigrationRunner` +
  `Version*` classes built with `PhpDb\Sql\Ddl`. Run via `php bin/migrate.php
  [migrate|rollback [n]|status]` or `make db-migrate` / `db-rollback` /
  `db-status`. State tracked in `schema_migrations`.

Never use the `@deprecated` `PhpDb\Adapter\Adapter::query()`; use
`executeQuery()` / `prepareQuery()` / `prepareStatementForSqlObject()`.

### Configuration system

`config/config.php` runs `ConfigAggregator` over, in order: framework
ConfigProviders, `App\ConfigProvider`, then every
`config/autoload/{,*.}{global,local}.php` file, then
`config/development.config.php` (only present in development mode). Later
providers override earlier ones. `*.global.php` is committed; `*.local.php` is
gitignored and machine-specific.

The merged config is cached to `data/cache/config-cache.php` in production. **Any
change to config files or ConfigProviders requires `composer clear-config-cache`
to take effect** (caching is off in development mode).

## Tests

PHPUnit 11, tests in `test/AppTest/` (PSR-4 `AppTest\`), mirroring `src/App/src/`.
Two suites: `composer test` (`unit`, no DB) and `composer test-integration`
(`integration`, `test/AppTest/Integration/` — hits the `mariadb` container,
runs migrations + `TRUNCATE` in `setUp`). `phpunit.xml.dist` sets
`failOnAllIssues="true"` — deprecations and warnings fail the suite, so use
PHPUnit attributes (`#[DataProvider]`), not `@` annotations.
`AppTest\InMemoryContainer` is a minimal PSR-11 stub for testing
factories without booting the real container.
