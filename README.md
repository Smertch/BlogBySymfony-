# Blog (Symfony)

Symfony **7** port of the Laravel `blog` project: same business features (users with roles + 2FA, posts, RSS feed, registration welcome mail dispatched via RabbitMQ, password reset, Swagger API doc, admin panel) — implemented with native Symfony components.

| Symfony equivalent in this project |
|---|---|
| Doctrine ORM (`App\Entity\*`, `App\Repository\*`) |
| Doctrine migrations (`migrations/`) |
| Symfony Security + `scheb/2fa-bundle` + custom controllers |
| Twig (`templates/`) |
| Symfony Mailer + Messenger (`symfony/amqp-messenger`) |
| EasyAdminBundle (`/admin`) |
| NelmioApiDocBundle + Swagger UI (`/swagger`) |
| **Apache Kafka** (KRaft mode, single broker) |
| Stateless firewall + `ApiTokenAuthenticator` |

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

```bash
php bin/console lint:twig templates
php bin/console lint:yaml config translations
php bin/console doctrine:schema:validate
vendor/bin/phpunit
```

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
