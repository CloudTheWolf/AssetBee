# AssetBee

[![Tests](https://github.com/CloudTheWolf/AssetBee/actions/workflows/tests.yml/badge.svg)](https://github.com/CloudTheWolf/AssetBee/actions/workflows/tests.yml)
[![Docker](https://github.com/CloudTheWolf/AssetBee/actions/workflows/docker.yml/badge.svg)](https://github.com/CloudTheWolf/AssetBee/actions/workflows/docker.yml)
[![Docker Hub](https://img.shields.io/docker/v/cloudthewolf/assetbee-site?label=Docker%20Hub&sort=semver)](https://hub.docker.com/r/cloudthewolf/assetbee-site)

AssetBee is a self-hostable IT asset and inventory platform for tracking people, hardware, virtual machines, cloud tenants, software, licences, assignments, and device health from one organization-aware dashboard.

The production image runs Laravel Octane on FrankenPHP and is published as [`cloudthewolf/assetbee-site`](https://hub.docker.com/r/cloudthewolf/assetbee-site).

## Features

- Organization-scoped hardware, virtual machine, identity, software, and cloud-tenant records
- Software licence, seat, renewal, assignment, and cost tracking
- Device inventory API with update, antivirus, encryption, login-provider, and SBOM data
- Inventory findings, downloadable PDF reports, and SOC 2 inventory-control evidence
- AWS EC2 discovery and CSV identity imports
- Organization roles, invitations, API keys, audit history, and subscription limits
- Password, two-factor, passkey, and optional Google sign-in support
- Optional Stripe billing for hosted deployments

## Quick start with Docker Hub

The included [`docker-compose.yaml`](docker-compose.yaml) starts AssetBee with MariaDB, Redis, a queue worker, and the scheduler. Docker Engine with the Compose plugin is required.

```bash
git clone https://github.com/CloudTheWolf/AssetBee.git
cd AssetBee
cp .env.production.example .env.production
```

Generate an application key and place the complete output in `APP_KEY` inside `.env.production`:

```bash
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

Before starting the stack, set at least these values in `.env.production`:

```dotenv
APP_KEY=base64:replace-with-the-generated-key
APP_URL=http://localhost:8000
DB_PASSWORD=replace-with-a-long-random-password
DB_ROOT_PASSWORD=replace-with-another-long-random-password
SESSION_SECURE_COOKIE=false
MAIL_MAILER=log
```

Then pull and start the published image:

```bash
docker compose --env-file .env.production -f docker-compose.yaml pull
docker compose --env-file .env.production -f docker-compose.yaml up -d
```

Open <http://localhost:8000> and create the first account.

The `SESSION_SECURE_COOKIE=false` and `MAIL_MAILER=log` values above are suitable only for a local evaluation. Before exposing AssetBee, configure SMTP, move `APP_URL` to HTTPS, and restore `SESSION_SECURE_COOKIE=true`.

Check the application and worker logs with:

```bash
docker compose --env-file .env.production -f docker-compose.yaml logs -f app queue scheduler
```

### HTTPS and reverse proxies

The Compose file creates a private bridge network named `assetbee`. The application binds to `127.0.0.1:8000` by default, while either optional proxy profile can publish it.

For Traefik with automatic HTTPS, point `DOMAIN` at the Docker host, set `TRAEFIK_ACME_EMAIL`, use an HTTPS `APP_URL`, and run:

```bash
docker compose --env-file .env.production -f docker-compose.yaml --profile with-traefik up -d
```

The Traefik profile publishes ports `80` and `443`, redirects HTTP to HTTPS, and stores its ACME certificates in the `traefik_letsencrypt` volume.

See Traefik's official [Docker Compose with Let's Encrypt HTTP challenge guide](https://doc.traefik.io/traefik/user-guides/docker-compose/acme-http/) for certificate setup details.

For an Nginx HTTP reverse proxy, use an HTTP `APP_URL`, set `SESSION_SECURE_COOKIE=false`, and run:

```bash
docker compose --env-file .env.production -f docker-compose.yaml --profile with-nginx up -d
```

The Nginx profile publishes port `80`. Terminate TLS in an upstream load balancer if this profile is internet-facing. Do not enable both proxy profiles on the same host ports.

To configure Let's Encrypt directly with Nginx, follow the official [Certbot instructions for Nginx](https://certbot.eff.org/instructions?ws=nginx).

## Configuration

`.env.production.example` documents the available production settings. The most important values are:

| Variable | Purpose                                                                             |
| --- |-------------------------------------------------------------------------------------|
| `APP_KEY` | Required Encryption key for your data. Generate once and keep it stable.            |
| `APP_URL` | Public application URL, including `https://` in production.                         |
| `APP_VERSION` | Docker image tag; defaults to `latest`. Pin a release tag for predictable upgrades. |
| `DOMAIN` | Hostname used by the selected reverse proxy.                                        |
| `TRAEFIK_ACME_EMAIL` | Let's Encrypt account email used by the Traefik profile.                            |
| `RUN_MIGRATIONS` | Runs pending migrations in the app container during startup.                        |
| `DB_*` | MariaDB/MySQL connection and bundled database credentials.                          |
| `REDIS_*` | Redis connection used for cache, sessions, and queues.                              |
| `MAIL_*` | SMTP delivery for verification, reset, and invitation emails.                       |
| `GOOGLE_*` | Optional Google OAuth and Workspace domain provisioning.                            |


## Image details
The image uses these runtime roles:

- `CONTAINER_ROLE=app` serves HTTP traffic.
- `CONTAINER_ROLE=queue` runs queue work.
- `CONTAINER_ROLE=scheduler` runs the scheduler.

Persist `/app/storage/app` for uploaded asset documents. Application logs are emitted to stderr by default; Compose also preserves `/app/storage/logs` and bind-mounts `./drone` at `/app/public/drone`.

## Updating and backups

Pin `APP_VERSION` to a release tag in production. To update:

```bash
docker compose --env-file .env.production -f docker-compose.yaml pull
docker compose --env-file .env.production -f docker-compose.yaml up -d
```

With `RUN_MIGRATIONS=true`, the app container waits for MariaDB and applies pending migrations before serving requests.

Back up both the MariaDB database and the `app_storage` volume. Redis is configured with append-only persistence, but it should be treated as cache, session, and queue state rather than the primary system of record.

## Local development

AssetBee requires PHP 8.3 or newer, Composer 2, Bun, MariaDB, and Redis. Laravel Sail is included for Docker-based development.

```bash
cp .env.example .env
composer install
php artisan key:generate
bun install
php artisan migrate
composer run dev
```

Or start the development environment with Sail:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

Run the project checks before submitting a change:

```bash
composer ci:check
```

That command checks formatting, runs PHPStan, and executes the Pest test suite.

## Container publishing

The Docker workflow builds pull requests without publishing. Pushes to `main` publish `main`, `latest`, and SHA tags; Git tags beginning with `v` publish semantic-version tags. The workflow also attaches build provenance and an SBOM, then synchronizes this README to the Docker Hub repository overview.

Repository maintainers must configure these GitHub Actions secrets:

- `DOCKERHUB_USERNAME`
- `DOCKERHUB_TOKEN` with permission to push the image and update its Docker Hub description

## Security

Please avoid publishing vulnerabilities in a public issue. Use GitHub's private vulnerability reporting for this repository when available, or contact the repository owner privately.

## License

The package metadata declares this project under the MIT licence.
