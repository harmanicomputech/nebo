# Deploying Nebo Stage

Nebo Stage runs on an ordinary PHP host (VPS or shared hosting with SSH) with MySQL 8. The web root must be the `public/` folder.

## Requirements

- PHP 8.3 or 8.4 with `pdo_mysql`, `mbstring`, `intl`, `gd`, `fileinfo`, `bcmath`, `zip`, OPcache on.
- MySQL 8 (utf8mb4).
- HTTPS. The PWA's service worker needs it and session cookies are HTTPS-only.
- Node 20+ only on the machine that builds assets. The server needs the built `public/build` folder, not Node.

## First install

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build                  # or upload public/build from your machine
cp .env.example .env && php artisan key:generate
# edit .env: see "Environment" below
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan nebo:create-admin --email=you@company.com
php artisan optimize
php artisan nebo:check-production        # must end with "Ready for production."
```

Never run `db:seed` without `--class` in production: the default seeder adds demo users and `DEMO-` equipment. `nebo:check-production` fails if it finds them.

## Environment

| Setting | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain` (links are generated as https) |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_ENCRYPT` | `true` |
| `DB_*` | your MySQL database |
| `MAIL_*` | a real mailer (SMTP, Mailgun, …) for customer emails and password resets |
| `QUEUE_CONNECTION` | `database` |

Then sign in and set the real company name, email, phone and address under **Settings**. Quotations and emails use them.

## Scheduler and queue (one cron line)

```
* * * * * cd /path/to/nebo && php artisan schedule:run >> /dev/null 2>&1
```

That single entry runs:

- the queue (customer emails, notifications) every minute, so no long-running worker is needed on shared hosting;
- `nebo:maintenance-reminders` daily at 07:00 Lagos;
- `nebo:expire-quotations` daily at 00:15 Lagos.

On a VPS you can run `php artisan queue:work` under Supervisor instead. The scheduled drain is then harmless.

## Every deploy

```bash
php artisan down
git pull                                  # or upload the new release
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force   # adds new permissions and reference data; never overwrites edits
php artisan optimize
php artisan up
```

Upload a fresh `public/build` whenever front-end files change. The service worker picks up the new assets on its own. Bump `VERSION` in `public/sw.js` only when its caching rules change.

## Security notes

- The web server must serve only `public/`. Uploaded documents live in `storage/app/private` and are streamed through permission checks, never linked directly.
- Security headers, including a Content-Security-Policy, are sent by `SecurityHeaders`. Inline scripts are not allowed, so don't add `<script>` blocks or `onclick=` attributes; use `data-action` or Alpine.
- Built assets are cached for a year (`public/.htaccess`), because their file names change with every build. HTML is never cached by the browser or the service worker.
- Back up the database and `storage/app/private` together, nightly.
