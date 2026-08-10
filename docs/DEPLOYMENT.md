# Orbitra production runbook

This runbook assumes PHP 8.2+, Composer 2, Node.js 20+, MySQL 8, HTTPS, a process supervisor, and private persistent storage. Run every command from the Orbitra release directory.

## First deployment

1. Create an empty MySQL 8 database and a least-privilege application user.
2. Copy `.env.example` to `.env`. Set `APP_ENV=production`, `APP_DEBUG=false`, canonical `APP_URL`, database credentials, `SESSION_SECURE_COOKIE=true`, mail, queue, broadcast, Stripe, OAuth, and VAPID values.
3. Install and build:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
php artisan migrate --force
php artisan optimize
```

Do not run `DemoSeeder` in production. `DatabaseSeeder` limits demo records to local/development, and `DemoSeeder` independently refuses a production environment.

## Release procedure

1. Create a MySQL backup and a matching snapshot of `storage/app/private`.
2. Install a reviewed release in a new directory and reuse the production `.env` and persistent storage mount.
3. Run `composer install --no-dev --optimize-autoloader`, `npm install`, `npm run build`, and `php artisan test` in CI. Generate, review, and commit a lockfile before adopting `npm ci`.
4. Run `php artisan down --retry=30`, `php artisan migrate --force`, `php artisan optimize`, `php artisan queue:restart`, then `php artisan up`.
5. Smoke-test the home page, manual login, an authorized workspace, global search, a private file download, and queue processing.

The queue supervisor should run:

```bash
php artisan queue:work --sleep=1 --tries=3 --timeout=90 --max-time=3600
```

Cron should invoke the scheduler every minute:

```cron
* * * * * cd /srv/orbitra && php artisan schedule:run >> /dev/null 2>&1
```

## Provider endpoints

- Stripe webhook: `POST /stripe/webhook`; copy the signing secret to `STRIPE_WEBHOOK_SECRET`.
- OAuth callbacks are listed in `.env.example` for Slack, Google Drive, GitHub, and Zoom. They must exactly match each provider dashboard and use HTTPS.
- Rebuild frontend assets after changing `VITE_PUSHER_*` variables.
- Keep VAPID keys stable or existing browser push subscriptions will stop working.

## Backup and restore

Keep database and private-storage snapshots from the same point in time. Encrypt backups, restrict access, copy them off-host, define retention, and test recovery regularly.

```bash
mysqldump --single-transaction --routines --triggers -u orbitra -p orbitra > orbitra.sql
tar -czf orbitra-private-storage.tar.gz storage/app/private
```

Restore into an isolated database first:

```bash
mysql -u orbitra -p orbitra_restore < orbitra.sql
tar -xzf orbitra-private-storage.tar.gz
php artisan migrate:status
php artisan test
```

After validation, switch the application to the restored database and matching storage while it is in maintenance mode. Run `php artisan optimize:clear`, `php artisan optimize`, restart workers, restore traffic, and verify authorized downloads.

## Rollback and incident checks

Application code can be switched back to the prior immutable release only when its schema is compatible. If the newest migration must be reversed, inspect it first, enter maintenance mode, back up again, and run `php artisan migrate:rollback --step=1 --force`. Restore the database and storage snapshots together when data was transformed or removed.

Monitor `storage/logs/laravel.log`, the `failed_jobs` table, queue depth, web server errors, webhook delivery dashboards, database capacity, and private-storage capacity. Use `php artisan queue:retry <id>` only after the underlying failure is understood and the job is safe to repeat.
