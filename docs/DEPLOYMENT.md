# Deploying Nebo Stage

Nebo Stage runs on an ordinary PHP host (VPS or shared hosting with SSH) with MySQL 8. The web root must be the `public/` folder.

**No terminal (DirectAdmin, cPanel)?** Build the upload package with `scripts/build-directadmin.sh` and follow its `INSTALL.txt` (also in `deploy/directadmin/`): extract the zip next to `public_html`, open the site and finish in the browser installer. See [Shared hosting without a terminal](#shared-hosting-without-a-terminal) below.

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

## Shared hosting without a terminal

`scripts/build-directadmin.sh [out.zip]` (needs Composer, Node and git on the build machine) produces `nebo-stage-directadmin.zip`:

- `nebo/`: the application with production dependencies, built assets' manifest, `.env.directadmin` (template) and an `.htaccess` that denies all access in case it is uploaded inside the web root;
- `public_html/`: the web root. Its `index.php` finds the app at `../nebo` (edit `$appPath` for subdomains), shows a plain message if PHP is older than 8.3, and on the first visit writes `.env` with a fresh key;
- `INSTALL.txt`: the owner's step-by-step guide.

The browser installer (`/install`, D69) checks PHP and folders, tests the database login, writes `.env` (production, HTTPS cookies when the URL is https, mail settings), then runs migrations, reference data, the administrator account and, if ticked, the demo data, one short step per page load (the longest about 15 s on MariaDB). A failed step shows the error and a retry button. When it finishes it writes `storage/app/installed.lock` and turns itself off; to reinstall, empty the database and delete that file and `.env`.

To update: back up the database, extract the new zip over the old files (`.env`, uploads and the lock are kept), then **Settings → System → Apply update**, which runs migrations and the reference seeder and clears compiled caches.

Tested end to end on Apache 2.4 + mod_php 8.3 + MariaDB 10.11 with a DirectAdmin-style layout, including the demo data (18 steps, 73 s).

## Scheduler and queue (one cron line)

```
* * * * * cd /path/to/nebo && php artisan schedule:run >> /dev/null 2>&1
```

That single entry runs `nebo:tick` every minute, which:

- sends queued customer emails and notifications, so no long-running worker is needed on shared hosting;
- runs `nebo:expire-quotations` once a day from 00:15 Lagos and `nebo:maintenance-reminders` once a day from 07:00 Lagos.

**No cron at all?** Set `NEBO_WEB_CRON=true` (the upload package does): the same work runs after page responses, at most once a minute. The daily jobs are deduplicated per day, so enabling both cron and the web fallback never sends reminders twice. Emails then go out on the next page visit rather than within the minute.

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
