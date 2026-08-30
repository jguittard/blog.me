# blog.me

A small blog application built on [Mezzio](https://docs.mezzio.dev/mezzio/) (Laminas)
— PSR-15 middleware, PHP 8.5, `php-db/phpdb` for persistence, `laminas-hydrator`
mapping SQL rows to immutable entities and DTOs. The reader frontend is
server-rendered `.phtml` (post list, single post, category browsing) styled with
Tailwind (Play CDN) to the shadcn/ui look.

The whole environment (Caddy TLS · nginx · php-fpm · MariaDB · Redis · MinIO ·
Mailpit) runs in Docker. See [`docker/README.md`](docker/README.md) for the stack
and [`CLAUDE.md`](CLAUDE.md) for the architecture.

## Requirements

- Docker + Docker Compose
- [`mkcert`](https://github.com/FiloSottile/mkcert) on the host (`brew install mkcert nss`)
- `make` (optional, but every command below has a `make` shortcut)

## Kick-off

```bash
git clone git@github.com:jguittard/blog.me.git
cd blog.me

make certs                 # mkcert -> docker/caddy/certs/blog.me*.pem
cp .env.example .env       # on Linux, set UID/GID to `id -u` / `id -g`

# add the local hostnames (macOS/Linux); `make hosts` prints this line
sudo sh -c 'echo "127.0.0.1 blog.me www.blog.me mail.blog.me s3.blog.me minio.blog.me" >> /etc/hosts'

make build                 # build the php image
make up                    # start the stack
make install               # composer install (in the container)
make db-migrate            # create the schema
```

Then open **<https://blog.me:8443>**.

> Caddy defaults to ports `8080`/`8443` so the stack starts even if another local
> proxy owns `80`/`443`. For bare `https://blog.me`, set `HTTP_PORT=80` /
> `HTTPS_PORT=443` in `.env` (nothing else may hold those ports) and `make up`.

| What | URL |
|------|-----|
| App | <https://blog.me:8443> |
| Mailpit (outgoing mail) | <https://mail.blog.me:8443> |
| MinIO console | <https://minio.blog.me:8443> — `minio` / `minio12345` |
| MinIO S3 API | <https://s3.blog.me:8443> (path-style) |

## Everyday commands

`make` targets run inside the container; anything not listed can be run with
`docker compose exec -u www-data php composer <script>`.

| Command | Does |
|---------|------|
| `make up` / `make down` | start / stop the stack |
| `make logs` | follow all container logs |
| `make sh` | shell into the php container |
| `make install` | `composer install` |
| `make test` | unit test suite (no database) |
| `make test-integration` | integration tests against the `mariadb` container |
| `make db-migrate` | apply pending migrations |
| `make db-rollback` | revert the last migration (`make db-rollback n=3` for more) |
| `make db-status` | list migrations and whether they are applied |
| `make seed` | load sample blog data (idempotent); `make seed-fresh` wipes first |
| `make covers` | generate post cover SVGs and upload them to MinIO |
| `make destroy` | stop the stack and drop the Redis/Caddy volumes |
| `make certs` | (re)generate the local TLS certificate |
| `make hosts` | print the `/etc/hosts` line you need |

Composer scripts (via `make sh` or `docker compose exec -u www-data php composer …`):

```bash
composer test                 # PHPUnit, unit suite
composer test-integration     # PHPUnit, integration suite (DB required)
composer cs-check             # phpcs (Laminas Coding Standard)
composer cs-fix               # phpcbf autofix
composer static-analysis      # psalm
composer clear-config-cache   # after changing config/ or a ConfigProvider
composer development-enable    # dev mode: no config cache, debug on
composer development-disable
```

## Database & migrations

Schema is managed with `php-db` `Sql\Ddl` migration classes in
`src/App/src/Infrastructure/Persistence/Migration/`. The runner tracks state in a
`schema_migrations` table.

```bash
make db-migrate                    # apply pending migrations
make db-rollback n=2               # revert the last 2
make db-status                     # what's applied

# equivalently, inside the container:
docker compose exec -u www-data php php bin/migrate.php migrate
```

Add a migration by creating `Version<UTC timestamp><Name>.php` extending
`AbstractMigration` and appending it to `MigrationRunnerFactory::MIGRATIONS`.

### Sample data

`bin/seed.php` (`make seed`) populates the blog with 3 authors, PPL theory
categories/tags and 30 posts about aircraft and flight training. It is
idempotent — re-running skips anything that already exists. `make seed-fresh`
truncates the blog tables first.

`make covers` generates a deterministic cover SVG per post (`bin/covers.php`) and
uploads them to the MinIO `uploads` bucket; ~3 in 4 posts get one. The seed
stores the object URL from `S3_PUBLIC_ENDPOINT`, so run `make covers` alongside
`make seed` and keep `S3_PUBLIC_ENDPOINT` in step with `HTTPS_PORT`.

Connection settings come from `DATABASE_URL` in `.env`
(`config/autoload/database.global.php`); MariaDB and MinIO data persist to
`./data/` on the host.

## Project layout

```
src/App/src/
  Handler/                     PSR-15 request handlers: Post{List,View},
                               Category{List,Posts}, Pagination
  Domain/
    Entity/                    final readonly entities (clone-with mutations)
    ReadModel/                 flat hydrate-only DTOs for JOIN queries
    Value/                     PostStatus enum, Slug / Email value objects
    Repository/                repository interfaces
  Infrastructure/Persistence/
    PhpDb/                     repository implementations (TableGateway + Sql)
    Hydrator/                  HydratorRegistry (ReflectionHydrator + strategies)
    Migration/                 Sql\Ddl migrations + runner
  templates/                   .phtml views (Tailwind Play CDN); layout + partials
config/                        pipeline, routes, ConfigProviders, autoload/*.php
test/AppTest/                  mirrors src/; Integration/ = DB-backed suite
docker/                        Dockerfile, Caddyfile, nginx, compose helpers
```

## Quality gates

```bash
composer cs-check && composer test && composer test-integration
```

CI-equivalent locally: `composer check` (cs-check + unit tests).
