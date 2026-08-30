# Local Docker environment

```
browser ──https──> caddy ──http──> nginx ──fastcgi──> php-fpm ──> public/index.php
                     │
                     ├── mail.blog.me   -> mailpit (web UI)
                     ├── s3.blog.me     -> minio  (S3 API, path-style)
                     └── minio.blog.me  -> minio  (web console)

php also talks to: mariadb, redis, minio, mailpit
```

## Services

| Service     | Image                | Purpose                          | Host access (default `.env`)             |
|-------------|----------------------|----------------------------------|------------------------------------------|
| caddy       | `caddy:2-alpine`     | TLS termination / reverse proxy  | `:8080`, `:8443` (`HTTP_PORT`/`HTTPS_PORT`) |
| nginx       | `nginx:1.27-alpine`  | Static + FastCGI to php-fpm      | via caddy                                |
| php         | built (`8.5-fpm`)    | Application runtime              | `docker compose exec -u www-data php`    |
| mariadb     | `mariadb:11.4`       | Database                         | `127.0.0.1:13306`                        |
| redis       | `redis:7-alpine`     | Cache / sessions / queues        | `127.0.0.1:13379`                        |
| minio       | `minio/minio`        | S3-compatible upload storage     | `127.0.0.1:19000` API, `:19001` console  |
| createbuckets | `minio/mc`         | One-shot: creates `uploads` bkt  | —                                        |
| mailpit     | `axllent/mailpit`    | Catches outgoing mail            | `127.0.0.1:18025` UI, `:11025` SMTP      |

All host port mappings are overridable in `.env`. The support-service ports are
bound to `127.0.0.1` and offset from the standard ones so they don't clash with
a locally-installed mysql/redis/mailpit — the app itself always talks to them by
service name on the Docker network (`mariadb:3306`, `redis:6379`, ...).

**Caddy defaults to 8080/8443** (`https://blog.me:8443`) so the stack comes up
alongside another local reverse proxy (Herd, a second compose stack, ...). For
bare `https://blog.me`, set `HTTP_PORT=80` / `HTTPS_PORT=443` in `.env` once
nothing else holds those ports.

### Data persistence

MariaDB and MinIO bind-mount to `./data/mariadb` and `./data/minio` on the host
(override with `MARIADB_DATA_PATH` / `MINIO_DATA_PATH`). This data survives
`docker compose down -v` / `make destroy` — delete the directories by hand to
reset. Redis and Caddy still use Docker-managed named volumes.

**MariaDB** was chosen over MySQL: drop-in compatible with the PHP/PDO and
Laminas ecosystem, ships a working container `healthcheck.sh`, LTS 11.4 release
line, and a permissive license.

## First-time setup

```bash
brew install mkcert nss          # once, if you don't have mkcert
cp .env.example .env             # adjust UID/GID on Linux: id -u / id -g
make certs                       # mkcert -> docker/caddy/certs/blog.me*.pem
```

Add to `/etc/hosts` (`make hosts` prints this):

```
127.0.0.1 blog.me www.blog.me mail.blog.me s3.blog.me minio.blog.me
```

Then:

```bash
make build
make up
make install                     # composer install inside the container
```

Open <https://blog.me:8443>. Mail UI: <https://mail.blog.me:8443>. MinIO console:
<https://minio.blog.me:8443> (`minio` / `minio12345`). (Drop the `:8443` if you
set `HTTPS_PORT=443`.)

## Everyday commands

```bash
make up / make down              # start / stop
make logs                        # tail everything
make sh                          # shell in the php container (as www-data)
make test                        # composer test in the container
make destroy                     # stop + wipe volumes (DB, uploads, redis)
```

## Xdebug

Off by default. Enable per-run:

```bash
XDEBUG_MODE=debug docker compose up -d php
```

IDE listens on the host; the container reaches it via `host.docker.internal`.
IDE key is `PHPSTORM` (see `docker/php/xdebug.ini`).

## Wiring the application

Connection details are passed to the `php` container as environment variables
(`docker-compose.yml` → `.env`). Read them from `config/autoload/*.local.php`
(gitignored) — e.g.:

| Variable            | Value inside the network                         |
|---------------------|-------------------------------------------------|
| `DATABASE_URL`      | `mysql://blog:blog@mariadb:3306/blog`           |
| `REDIS_URL`         | `redis://redis:6379`                            |
| `S3_ENDPOINT`       | `http://minio:9000` (use path-style addressing) |
| `S3_PUBLIC_ENDPOINT`| `https://s3.blog.me` (URLs handed to browsers)  |
| `S3_BUCKET`         | `uploads` (public read)                         |
| `MAILER_DSN`        | `smtp://mailpit:1025`                           |

`nginx` sets `HTTPS=on` for FastCGI so the app builds `https://` URLs even though
php-fpm itself speaks plain HTTP to nginx.
