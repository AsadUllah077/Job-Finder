# JobFinder installation

JobFinder is a Laravel 12 application using Blade, Tailwind 4, Alpine.js, Lucide icons, Eloquent, database queues, and Laravel mail notifications. It collects PHP/Laravel jobs through approved public APIs, with a private application tracker and configurable matching. Laravel 12 is the supported choice for this workspace's installed PHP 8.2.4; upgrading PHP is necessary before adopting framework releases that require newer runtimes.

## Local setup

1. Install PHP 8.2+ with PDO MySQL, curl, mbstring, DOM/XML, openssl, fileinfo, and zip; Composer 2; Node 22+; MySQL 8+ or a compatible MariaDB. The implementation was run against MariaDB 10.4.28 supplied by local XAMPP. For production use a currently supported database release.
2. Install dependencies and create the environment file:

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

PowerShell uses `Copy-Item .env.example .env` instead of `cp`. If Windows' `npm` launcher is broken, invoke `node "C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js" ci` and `node "C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js" run build`.

3. Create a database and restricted database account, adjusting host/password to your environment:

```sql
CREATE DATABASE jobfinder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jobfinder'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT ALL PRIVILEGES ON jobfinder.* TO 'jobfinder'@'localhost';
```

4. Update `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. Set `APP_URL` to your actual local URL. Then run:

```sh
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

5. Open `http://127.0.0.1:8000`. Browse public listings or register an account. No administrator or default-password user is seeded. Grant your registered account admin access from a trusted terminal:

```sh
php artisan users:admin your-email@example.com
```

6. Start a queue worker in a second terminal and a scheduler in a third:

```sh
php artisan queue:work database --sleep=3 --tries=3 --timeout=900
php artisan schedule:work
```

7. In another terminal, request collection (or use the admin Source management screen):

```sh
php artisan jobs:sync
```

The default seeder enables Arbeitnow and Remotive without credentials. Other sources are disabled until configured. Manual requests respect provider cooldowns and queue uniqueness. If a source was just attempted, another request may be skipped. Keep the worker running; a “queued” message is not evidence that fetching succeeded. Review actual source state and history in admin or `php scripts/source-status.php`.

## Workspace verification

At implementation time, Remotive completed a real API request and stored **three matching listings** in MySQL/MariaDB; Arbeitnow's first request was blocked by restricted network access and is recorded as a failure. All six adapters were tested using mocked HTTP; Greenhouse/Lever require a board, and Adzuna requires credentials. There are no seeded fictional “live” jobs. This is an observation from one run, not a guarantee of ongoing provider availability.

```sh
php artisan test
php scripts/verify-install.php
php artisan schedule:list
npm run build
```

The test suite uses an isolated SQLite in-memory database, not your configured MySQL data, and blocks stray HTTP requests. It covers registration/login/password reset, source payloads, normalization and safe URLs, pagination, locks, cooldowns, failures, idempotent sync, source-specific IDs, filters, history, scoring, user ownership, application status, CSV formula protection, admin authorization, alerts, and signed unsubscribe. The read-only installation check separately exercises the real configured MySQL full-text query and stored listing URLs.

An optional development-only seeder is available:

```sh
php artisan db:seed --class=DemoSeeder
```

Every demo row has `is_demo=true`, a prominent DEMO label, fictional company/title, and disabled Apply button. Demo rows are excluded from alerts. Do not run this seeder in production. The regular seeder creates only source configuration and never erases existing source settings.

## Features and behavior

- Public job listing/detail pages; authenticated dashboard with collection metrics, preference matches, and recent activity.
- Title/keyword/company, location/country/city, work style, type, experience, salary/currency/period, source, publication-date, saved/unsaved filters; newest/oldest/relevance ordering and pagination. Publication dates remain distinct from discovery timestamps. Unknown data is not guessed.
- MySQL full-text keyword search. Natural-language searches ignore common connecting words and numeric years; they do not infer an exact years-of-experience requirement when the source provides no structured field.
- Private saves, notes, application statuses, applied/interview dates, and CSV export. Saving the same job twice is idempotent. Policies enforce ownership; ordinary users cannot access admin controls.
- Configurable keyword/location preferences, worldwide/remote choices, salary floors, experience/types, and scoring weights. Match scores use PHP rules and are explicitly estimates. No AI API is called.
- One configurable alert rule per user, daily digest or 15-minute checks, opt-in, delivery tracking, and signed unsubscribe confirmation. SMTP configuration is required before delivery is enabled.
- Admin enable/disable controls, reviewed board settings, interval and keyword/location defaults, user roles, source run counts/errors, and failed queue metadata.
- Source-specific uniqueness and source-safe request construction. Only explicit expiration evidence marks a job inactive; disappearance from one partial feed does not.

## Configuration and operations

See [source setup and API documentation](docs/SOURCES.md) for credentials, board slugs, attribution, quota rules, and current verification status. See [production deployment](docs/DEPLOYMENT.md) for web-server configuration, scheduler/worker supervision, security, mail setup, backups, monitoring, and scaling limitations.

Important: queue messages use `queue_jobs`, while normalized listings use `jobs`. Leave `DB_QUEUE_TABLE=queue_jobs`. Keep the queue retry interval (960 seconds) above the worker timeout (900 seconds). All deployment nodes must share a lock-capable cache.

The local `.env` is ignored and contains the generated app key and this machine's database connection. `.env.example` contains placeholders only. Never commit secrets or logs.

## Architecture

`app/Services/Sources` defines the source interface, reviewed registry, and six adapters. `JobNormalizer` converts source rows to a shared format, `SyncSource` performs queued upserts and run tracking, `JobSearch` builds filtered SQL and relevance ordering, and `RelevanceScorer` supplies preference estimates and deterministic alert rules. Controllers use Form Requests, policies protect private rows, and Blade components share layout, cards, and tracker fields. `SendJobAlerts` processes opted-in users through Laravel notifications; `routes/console.php` declares scheduling and operational commands.

Extend source support with a reviewed adapter and tests, not a user-entered feed URL. Arbitrary server-side fetching is intentionally unsupported. LinkedIn, Rozee, and Bayt are provided as external search links only.
