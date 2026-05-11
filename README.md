# Blog (Symfony)

[![CI](https://github.com/Smertch/BlogBySymfony-/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Smertch/BlogBySymfony-/actions/workflows/ci.yml)
[![Docker](https://github.com/Smertch/BlogBySymfony-/actions/workflows/docker.yml/badge.svg?branch=main)](https://github.com/Smertch/BlogBySymfony-/actions/workflows/docker.yml)

Symfony **7** port of the Laravel `blog` project: same business features (users with roles + 2FA, posts, RSS feed, registration welcome mail dispatched asynchronously, password reset, Swagger API doc, admin panel) — implemented with native Symfony components.

| Laravel original | Symfony equivalent in this project |
|---|---|
| Eloquent (`App\Models\*`) | Doctrine ORM (`App\Entity\*`, `App\Repository\*`) |
| MySQL 8 | **PostgreSQL 16** |
| Laravel migrations | Doctrine migrations (`migrations/`) |
| Fortify (login, register, 2FA, password reset) | Symfony Security + `scheb/2fa-bundle` + custom controllers |
| Blade views (`resources/views/`) | Twig (`templates/`) |
| Mail + `vladimir-yuldashev/laravel-queue-rabbitmq` | Symfony Mailer + Messenger via a **custom Kafka transport** (`App\Messenger\Transport\Kafka`) |
| RabbitMQ | **Apache Kafka** (KRaft mode, single broker) |
| Filament admin panel | EasyAdminBundle (`/admin`) |
| Swagger blade | NelmioApiDocBundle + Swagger UI (`/swagger`) |
| Sanctum (`/api/user`) | Stateless firewall + `ApiTokenAuthenticator` |

## Requirements

- **Docker** and **Docker Compose** (recommended for full stack)
- **PHP 8.3+** and **Composer 2** (on the host for `composer install` and CLI commands)
- For the host PHP you need: `ext-pdo_pgsql`, `ext-intl`, and (optionally, only if you run the Messenger worker on the host) `ext-rdkafka`. Inside the `php-fpm` Docker image these are already installed.

## Quick start (Docker)

1. Clone the repository and `cd` into the project root.

2. Environment file:

   ```bash
   cp .env.example .env
   ```

   Adjust values if needed. Inside Docker, `php-fpm` and the worker get `DATABASE_URL`, `MAILER_DSN`, and `MESSENGER_TRANSPORT_DSN` from `docker-compose.yml` (overrides `.env`).

3. Install PHP dependencies:

   ```bash
   docker compose run --rm php-fpm composer install
   ```

   Or, on the host (PHP 8.3+):

   ```bash
   composer install
   ```

4. Start the containers:

   ```bash
   docker compose build
   docker compose up -d
   ```

   Wait until Postgres and Kafka are healthy (the `php-fpm` service depends on them).

5. Run migrations:

   From the host (Postgres is exposed on port **1527**):

   ```bash
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

   Or inside the app container:

   ```bash
   docker compose exec php-fpm php bin/console doctrine:migrations:migrate --no-interaction
   ```

6. (optional) Create an admin user:

   ```bash
   docker compose exec php-fpm php bin/console app:create-admin admin@example.com Admin "password"
   ```

7. Open the app:

   - Site: **http://localhost:1525**
   - Mailhog UI: **http://localhost:1526**
   - Kafka UI (Provectus): **http://localhost:1530**
   - Admin panel: **http://localhost:1525/admin**
   - Swagger UI: **http://localhost:1525/swagger**

### Published ports

| Service     | Host port | Purpose                                            |
|-------------|-----------|----------------------------------------------------|
| webserver   | 1525      | HTTP (Nginx)                                       |
| mailhog     | 1526      | Mail UI                                            |
| postgres    | 1527      | PostgreSQL (CLI/tools on host)                     |
| kafka       | 1529      | Kafka `EXTERNAL` PLAINTEXT listener (from host)    |
| kafka-ui    | 1530      | Provectus Kafka UI                                 |

### Database: host vs container

- **`.env` for `bin/console` on the host** (e.g. `doctrine:migrations:migrate`): use `127.0.0.1:1527` (mapped Postgres port).
- **PHP-FPM in Docker** gets `DATABASE_URL=postgresql://user:password@postgres:5432/blog...` from `docker-compose.yml`, so the web app talks to Postgres by service name.

### Kafka: host vs container

The Kafka container advertises **two** listeners (KRaft single-broker setup):
- `PLAINTEXT://kafka:9092` — used by other Docker services (`php-fpm`, `queue-worker`, `kafka-ui`)
- `EXTERNAL://localhost:1529` — used by CLI/tools on your host machine

The host `.env` therefore uses `kafka://127.0.0.1:1529?topic=messages&group_id=blog`, while the containers receive `kafka://kafka:9092?...` from `docker-compose.yml`.

Do not commit `.env` (it is listed in `.gitignore`).

## Running the queue worker

The Laravel project queued the registration welcome email through RabbitMQ. In Symfony, the equivalent is `Symfony Messenger` with the **custom Kafka transport**:

```bash
docker compose up -d queue-worker
# or, on the host (requires ext-rdkafka):
php bin/console messenger:consume async -vv
```

The transport (`App\Messenger\Transport\Kafka\KafkaTransport`) is registered automatically via `App\Messenger\Transport\Kafka\KafkaTransportFactory` (tagged `messenger.transport_factory`). The DSN syntax is:

```
kafka://broker1:9092,broker2:9092?topic=messages&group_id=blog
        &auto_offset_reset=earliest      (optional, default: earliest)
        &consume_timeout_ms=10000        (optional, default: 10000)
        &flush_timeout_ms=10000          (optional, default: 10000)
        &commit_async=0                  (optional, default: 0)
        &consumer_<rdkafka.cfg>=<value>  (any rdkafka consumer option, dots replaced by _)
        &producer_<rdkafka.cfg>=<value>  (any rdkafka producer option, dots replaced by _)
```

## Quality checks

Most workflows have a `make` shortcut (see `make help`):

```bash
make install        # composer install
make lint           # Twig + YAML + DI container linting
make cs             # PHP-CS-Fixer (dry-run)
make cs-fix         # PHP-CS-Fixer (apply)
make phpstan        # PHPStan static analysis (level 5)
make test           # PHPUnit
make ci             # full local CI suite (composer validate + lint + cs + phpstan + test)
```

Or directly:

```bash
composer validate --strict --no-check-publish
php bin/console lint:twig templates
php bin/console lint:yaml config translations --parse-tags
php bin/console lint:container
php bin/console doctrine:schema:validate
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
vendor/bin/phpunit
```

## CI/CD

GitHub Actions workflows live under `.github/workflows/`:

### `ci.yml` — Continuous Integration

Triggered on every push and pull request to `main`. Runs in parallel:

| Job              | What it does                                                                 |
|------------------|------------------------------------------------------------------------------|
| `composer-validate` | `composer validate --strict --no-check-publish`                              |
| `lint`           | `lint:twig`, `lint:yaml`, `lint:container`                                   |
| `cs-fixer`       | `php-cs-fixer fix --dry-run --diff`, reported as checkstyle annotations      |
| `phpstan`        | PHPStan level 5 with `phpstan-symfony` + `phpstan-doctrine`                  |
| `tests`          | `doctrine:migrations:migrate` + `doctrine:schema:validate` + `phpunit` against a Postgres 16 service container; Messenger uses `in-memory://` and Mailer uses `null://null` |

The cache is keyed off `composer.lock`/`composer.json` and shared across jobs.

### `docker.yml` — Container images

Triggered on push to `main`, semver tags (`v*.*.*`), or manual dispatch. Builds two images in a matrix and pushes them to **GHCR**:

- `ghcr.io/smertch/blogbysymfony-/php-fpm`
- `ghcr.io/smertch/blogbysymfony-/nginx`

Tags follow the [`docker/metadata-action`](https://github.com/docker/metadata-action) defaults: branch name, semver, and `sha-<short>`. Layer cache is stored in GitHub Actions cache (`type=gha`, scope per image).

### `dependabot.yml`

Weekly updates for:

- Composer (grouped: `symfony/*`, `doctrine/*`, dev-tooling)
- GitHub Actions
- Docker images in `docker/php-fpm/` and `docker/nginx/`

### Required permissions

- The `docker.yml` workflow needs `packages: write` (already set in the workflow) so the default `GITHUB_TOKEN` can push to GHCR — no extra secrets required.
- If you later add deploy jobs, store credentials as GitHub repository secrets and reference them via `${{ secrets.* }}`.

## Project layout (essentials)

- `bin/console` — Symfony CLI entry point
- `config/` — bundle / framework / security / messenger / mailer configs
- `migrations/` — Doctrine migrations (PostgreSQL schema)
- `public/` — web root (`index.php` is the front controller)
- `src/`
  - `Entity/`, `Repository/` — Doctrine entities and repositories
  - `Controller/` — HTTP controllers
  - `Controller/Admin/` — EasyAdmin dashboards & CRUDs
  - `Form/` — Symfony Form types
  - `Message/`, `MessageHandler/` — Messenger messages and handlers
  - `Messenger/Transport/Kafka/` — Custom Kafka transport for Messenger
  - `Security/` — API token authenticator
  - `Enum/` — value enums (e.g. `UserRole`)
- `templates/` — Twig views (mirroring the Laravel `resources/views/` layout)
- `translations/` — translation catalogs (mirroring `lang/en/`)
- `docker/` — Nginx + PHP-FPM Dockerfiles, php-ini overrides
- `docker-compose.yml` — local stack (Nginx, PHP-FPM 8.3, Postgres 16, Redis, Kafka, Kafka UI, Mailhog)

## License

MIT.
