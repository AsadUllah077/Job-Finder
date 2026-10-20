# Production deployment

The application requires a PHP host and MySQL/MariaDB. It cannot run on static hosting or the Cloudflare Workers runtime. No public deployment was made as part of this local implementation.

## Server

Use PHP 8.2+ (8.3+ recommended), Composer 2, MySQL 8+ or a supported MariaDB version, and Node 22+ for asset builds. Enable PDO MySQL, mbstring, curl, openssl, fileinfo, DOM/XML, tokenizer, ctype, and zip. SQLite is needed for the default test suite. Configure Nginx or Apache with **only `public/` as the document root**. Deny dotfiles; keep `.env`, logs, and backups inaccessible over HTTP. Use HTTPS. Keep the PHP runtime and dependencies patched.

```sh
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
php artisan queue:restart
```

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, a unique `APP_KEY`, a restricted database user, and real SMTP settings. Generate the application key only once; preserve it across deployments. Do not copy a local `.env` to production. Back up MySQL and test restores. The web and worker users need write access to `storage/` and `bootstrap/cache/` only.

Serve through PHP-FPM. Example Nginx location:

```nginx
root /srv/jobfinder/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
location ~ /\. { deny all; }
```

Use trusted proxy configuration appropriate to your load balancer rather than trusting arbitrary forwarded headers. Security headers and private response caching are supplied by middleware. Session cookies are HttpOnly and SameSite=Lax. Add an appropriate CSP for your deployment; Alpine's default build uses dynamic expressions, so a strict no-eval CSP requires the Alpine CSP build and corresponding template changes.

## Queue worker

Queue messages live in `queue_jobs`; job listings live in `jobs`. **Do not change `DB_QUEUE_TABLE` to `jobs`.** The timeout must remain below `DB_QUEUE_RETRY_AFTER=960`. Use a shared database or Redis cache for locks across all web/worker nodes. All nodes must use the same cache prefix and application key.

Example Supervisor config:

```ini
[program:jobfinder-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /srv/jobfinder/artisan queue:work database --sleep=3 --tries=3 --timeout=900 --max-time=3600
directory=/srv/jobfinder
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/srv/jobfinder/storage/logs/worker.log
stopwaitsecs=960
```

Run `supervisorctl reread`, `supervisorctl update`, and restart the worker group. Run `php artisan queue:restart` after every code/config deployment. Windows development can use a separate terminal with the same `queue:work` command.

Failed sync attempts store a sanitized error and preserve jobs. Recoverable attempts release back to the queue after at least the provider cooldown. After three failed attempts, queue failure metadata is recorded. Inspect with `php artisan queue:failed`; use `php artisan queue:retry <uuid>` after resolving configuration. Never expose raw queue payloads, exceptions, or logs through a public route. Queue timeout failures are also recorded against their source.

## Scheduler

Linux crontab for the application user:

```cron
* * * * * cd /srv/jobfinder && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

On Windows use Task Scheduler every minute, executable `C:\xampp1\php\php.exe`, arguments `artisan schedule:run`, and start-in set to this project directory. For local development, `php artisan schedule:work` is convenient.

The scheduler checks due sources every minute. Admin settings control the interval (minimum 60 minutes); provider-specific limits take precedence. Remotive and Remote OK use a minimum of six hours. Alerts process every fifteen minutes for “soon after discovery” and daily at **08:00 Asia/Karachi**. Explicit expiration timestamps are checked hourly. Missing results do not expire a job because feeds may be partial or paginated. Retention decisions must not rely on age alone.

The application stores timestamps in UTC and displays dates/interview appointments in Asia/Karachi, configured through `jobfinder.display_timezone`. Interview input is converted to UTC before storage.

`withoutOverlapping`, unique queue dispatch, a source execution lock, last-check timestamps, and Retry-After cooldowns protect synchronization. Do not manually clear these locks to force extra provider requests. A killed worker's execution lock expires after 930 seconds; unique dispatch locks expire after 48 hours as a recovery backstop.

## Mail and alerts

Set the transport variables in `.env.example` to your provider's values and `JOB_ALERTS_MAIL_ENABLED=true`. Use a verified sender domain and configure SPF/DKIM as required by your provider. After changing environment values, rebuild config cache and restart queue workers. Laravel `log` and `array` transports intentionally do not count as email configuration.

Users must opt in. Each user's preferences define one alert rule (keywords OR each other; other filter groups AND each other). Only live, active, non-demo jobs first discovered after opt-in qualify. Salary floors require explicit annual pay in the selected currency; no conversion or salary guessing occurs. A digest contains up to 50 jobs, with remaining unsent matches available for later runs. Signed unsubscribe pages require a CSRF-protected confirmation; a mail scanner following a link cannot unsubscribe the user.

Mail acceptance is recorded after the transport returns. Per-user locks and a unique `(user_id,job_id)` delivery record prevent normal repeat delivery. **SMTP is not transactional**: a process crash after SMTP accepts a message but before the delivery record commits can cause a duplicate on retry. Provider delivery, bounces, and spam filtering are outside Laravel's acceptance acknowledgment; monitor them through your mail provider. This app does not claim exactly-once transport delivery.

## Operations and validation

`GET /up` checks application boot. Also monitor database connectivity, queue age, failures, storage usage, and source last-success timestamps. Review source API terms periodically; disable any source if permission changes. Source configuration never accepts arbitrary feed URLs; new providers require a reviewed adapter with a fixed host and redirect policy.

Run `php artisan test` before deployment. The automated suite is isolated on SQLite and uses mocked HTTP. `php scripts/verify-install.php` provides a read-only MySQL check once live jobs exist; `php scripts/source-status.php` prints sanitized source state. Browser layout QA is still recommended on real desktop and mobile devices; no browser automation tool was available in the implementation environment.

Large catalogs: keyword searches use a MySQL full-text index; exact country and enum filters use indexes. Ordinary search is paginated, and overview counts are cached for 60 seconds. Personalized relevance sorting scans the SQL-filtered result set to calculate exact per-user scores in PHP, retaining ID/score pairs for sorting. For millions of jobs or many users, move personalized ranking to a dedicated indexed search service or maintained score table. No load-test or scale claim is made.
