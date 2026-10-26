# P4DC Cyber Range

A Laravel 12 starter for the P4DC cybersecurity learning platform. This version includes individual registration/login, contestant pages, optional team creation and invitations, a protected administrator dashboard, profile details, and a PostgreSQL Docker environment. Challenge content, flag submission, score awarding, and challenge execution are intentionally not implemented.

## Requirements

- Docker Desktop with the Compose plugin (recommended)
- Alternatively, PHP 8.3+, Composer 2, PostgreSQL 16+, and the `pdo_pgsql` PHP extension

## Run with Docker

From this directory:

```powershell
Copy-Item .env.example .env
```

Set `APP_KEY` in `.env` to a fresh application key. Generate one with PHP 8.3:

```powershell
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copy the generated value into `.env`. Change `DB_PASSWORD` from its local development value before exposing the database or app outside your machine. Then start the services and apply migrations:

```powershell
docker compose up --build -d
docker compose exec app php artisan migrate --force
```

Open [http://localhost:8080](http://localhost:8080). PostgreSQL is available on `localhost:5433` for local database tools. The app connects to the Compose service on its private network. Override `APP_FORWARD_PORT` or `DB_FORWARD_PORT` in `.env` if those local ports are occupied.

If PowerShell says `docker` is not recognized for this per-user Docker Desktop installation, add its CLI folder for the current session:

```powershell
$env:Path = "$env:LOCALAPPDATA\Programs\DockerDesktop\resources\bin;$env:Path"
```

Useful commands:

```powershell
docker compose logs -f app
docker compose exec app php artisan migrate:status
docker compose exec app composer install
docker compose exec -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app php artisan test
docker compose down
```

`docker compose down` preserves the database volume. To permanently delete the local database as well, use `docker compose down -v`.

### First administrator

`ADMIN_EMAIL` and `ADMIN_USERNAME` in `.env` identify the primary admin independently of a row ID. The local `.env` values are configured as `brnrdolrnz@gmail.com` and `padcadmin`; `.env.example` leaves them blank so deployments must provide their own identity. The password is never stored in environment variables.

Bootstrap is an explicit one-time/manual operation. It creates the account only when neither configured identifier exists; if a matching account already exists, it only ensures Admin/active status and preserves its password, username, and email. The password is entered through hidden terminal prompts:

```powershell
docker compose exec app php artisan p4dc:bootstrap-admin
```

This command does not run at container startup. For an explicit password recovery, run `p4dc:recover-admin`; confirm the operation and enter the new password through hidden prompts. Recovery changes only the configured primary account, enforces the password minimum, and writes an audit event without recording the password.

Before creating the account, configure `ADMIN_EMAIL` and `ADMIN_USERNAME` in `.env`; the Compose service forwards these values. The bootstrap command uses that same app container and PostgreSQL database. It prompts for the password without echoing it.

The standard seeder (`php artisan db:seed`) is safe to rerun: it never creates a passwordless account, never changes a password or profile identity, and only reconciles role/status for an existing configured account. If none exists, it directs the operator to the bootstrap command. Normal registration only creates Contestant accounts.

PostgreSQL data is stored in the named `p4dc-postgres` volume. `docker compose restart` and container recreation preserve it. Do not use `docker compose down -v` for routine restarts; that explicitly deletes the database volume. Migrations are additive and do not remove user accounts.

## Run without Docker

Use PHP 8.3+ with `pdo_pgsql` and a running PostgreSQL database. Create an empty database and database user, copy `.env.example` to `.env`, set the database host/port and credentials, and generate `APP_KEY` as above. Then run:

```powershell
composer install
php artisan migrate
php artisan serve
```

Visit the URL printed by `php artisan serve` (usually `http://127.0.0.1:8000`). Composer creates `composer.lock` on the first dependency resolution; keep that lockfile under version control for reproducible installs.

## Tests

Feature tests use in-memory SQLite and cover registration, pipe-containing usernames, the eight-character password minimum, contestant/admin isolation, team creation/join, invitation regeneration, one-active-team rules, captain permissions/transfers, history-preserving admin actions, captain-account deletion and promotion protections, solve attribution, verified-only rankings, and login/logout.

```powershell
composer install
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php artisan test
```

The migrations create the `users`, `sessions`, teams/membership history, empty challenge metadata, submissions, individual verified solves with optional `team_id_at_solve`, and administrative audit tables. They do not seed sample users, teams, challenges, or scores. Individual points come only from a user's verified solves. Team points are the sum of individual verified solves attributed to that team at verification time; membership changes never transfer historical points. A solve is unique per user/challenge, and partial unique indexes enforce one active team membership per user while retaining past rows. Individual and team rankings remain admin-only and sort points descending with username/team name ascending as the tie-breaker.

Team creation assigns the creator as captain and member in one transaction. Invitation codes are 24 cryptographically random bytes encoded as hex; the database stores a SHA-256 lookup hash and an encrypted copy so only the captain's team page can display/copy it. Joining and captain operations are rate-limited where appropriate. Regenerating replaces both values, invalidating the old code. Membership joins, leaves, removals, captain transfers, and team changes create audit entries without invitation secrets. A captain must transfer captaincy before leaving; the database restricts deleting a captain account while their team references them.

Contestants can only view a team profile while they are an active member. Membership contributions are calculated for each membership interval using verified solves between `joined_at` and `left_at`; team totals query the immutable `team_id_at_solve` value. This preserves historical attribution through leaves and later joins without moving points between teams.

## Production and hosting

This image serves PHP through Apache on port 80 and stores state in PostgreSQL. Put it behind a TLS-terminating reverse proxy or deploy on a PHP-compatible container platform. For production, set `APP_ENV=production`, `APP_DEBUG=false`, a private `APP_KEY`, strong database credentials, the public HTTPS `APP_URL`, and `SESSION_SECURE_COOKIE=true`; do not publish the database port publicly. Keep secrets in the host's environment/secret manager, not in source control. Back up PostgreSQL and restrict database access to the app network.

Vercel does not natively host this persistent Laravel/PHP container and PostgreSQL service; use a Docker-compatible VPS or a PHP application host instead. No deployment has been attempted.

## Current scope and future conventions

Future flag strings use the `P4DC{...}` shape. Format-only examples are `P4DC{example_flag}` and `P4DC{welcome_to_p4dc}`; neither is a challenge flag. `config/ctf.php` records the planned categories, difficulty levels, score policy, and shape pattern; there is no flag submission endpoint or expected flag data in this version. A future validator must validate the submitted shape and compare the submitted value to the server-side expected flag. Never send expected flags to a browser. Challenge execution, when added, must use separately isolated containers with no access to the application or database network.
