# JobFinder

A functional Laravel job aggregator for PHP and Laravel opportunities, with public search, a personal dashboard, private application tracking, configurable alerts, and administrator source management.

Built with Laravel 12, Blade, Tailwind CSS 4, Alpine.js, Lucide, MySQL/MariaDB, Laravel queues, and PHPUnit. PHP 8.2+ is supported to match the installed environment.

- [Installation and local usage](INSTALLATION.md)
- [Source API setup, attribution, and verification status](docs/SOURCES.md)
- [Production deployment, workers, scheduler, and mail](docs/DEPLOYMENT.md)

The local database contains three real matching listings from a successfully verified Remotive fetch. The regular seeder creates source configuration only. Other adapters have mocked test coverage; credentials or board configuration are required where documented.

After completing installation:

```sh
php artisan serve
php artisan queue:work database --tries=3 --timeout=900
php artisan schedule:work
```

Run these in separate terminals. Register an account, then use `php artisan users:admin your-email@example.com` to grant administrator access. Trigger collection with `php artisan jobs:sync` or from the admin screen.

```sh
php artisan test
npm run build
php scripts/verify-install.php
```

The automated suite uses isolated SQLite and mocked HTTP; the installation check reads the configured MySQL database. No external API keys are needed for tests. Email delivery requires a configured transport and `JOB_ALERTS_MAIL_ENABLED=true`.
