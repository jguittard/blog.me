# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A [Mezzio](https://docs.mezzio.dev/mezzio/) (Laminas) PSR-15 middleware application,
scaffolded from `mezzio/mezzio-skeleton`. Runtime stack: laminas-servicemanager
(DI), mezzio-fastroute (routing), mezzio-laminasviewrenderer (templates),
laminas-diactoros (PSR-7). PHP 8.5.

The application code so far is essentially the skeleton default plus a
`GET /api/ping` endpoint (`App\Handler\PingHandler`, returns `{"ack": <time>}`).

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
  new handler must be registered here: stateless handlers under `invokables`,
  handlers needing constructor dependencies under `factories` (mapped to a
  `*Factory` class). Template namespace → path mappings live in `getTemplates()`.
- **Handlers** (`src/App/src/Handler/`) — one class per endpoint implementing
  `Psr\Http\Server\RequestHandlerInterface::handle()`, returning a Diactoros
  `JsonResponse` / `HtmlResponse`. Factories receive the PSR-11 container and
  pull collaborators from it (see `HomePageHandlerFactory`).
- **Templates** (`src/App/templates/`) — `.phtml` for laminas-view, referenced as
  `namespace::name` (e.g. `app::home-page`). `layout::default` wraps pages.

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

`HomePageHandler` branches on the concrete container/router/template classes to
render "what's installed" info — this is skeleton demo code, not a pattern to
follow for real handlers.

## Tests

PHPUnit 11, tests in `test/AppTest/` (PSR-4 `AppTest\`), mirroring `src/App/src/`.
`phpunit.xml.dist` sets `failOnAllIssues="true"` — deprecations and warnings fail
the suite. `AppTest\InMemoryContainer` is a minimal PSR-11 stub for testing
factories without booting the real container.
