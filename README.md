# Nebo Stage — Event Production Operations Platform

Inventory, bookings, availability, allocation, logistics, maintenance, CRM and quotations for **Nebo Stage**, a nationwide (Nigeria) event production and technical services company. It has two parts: a public site where customers request a production, and an internal operations system for staff. It installs as a PWA on phones, tablets and desktops.

- **Architecture and decisions:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Database design:** [docs/DATABASE.md](docs/DATABASE.md)
- **Roadmap and phase status:** [docs/ROADMAP.md](docs/ROADMAP.md)

## Stack

Laravel 13 (PHP 8.3+) · MySQL 8 / MariaDB 10.6+ (SQLite for tests) · Blade + Tailwind CSS v4 + Alpine.js (Vite) · spatie/laravel-permission · Lucide icons · self-hosted Inter / Space Grotesk fonts.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # or set DB_* for MySQL
php artisan migrate --seed            # roles, permissions, inventory reference data, demo users and demo equipment
npm run build                         # or: npm run dev
php artisan serve
```

Open <http://localhost:8000> for the public site and <http://localhost:8000/app> for the operations system.

### Demo accounts (local only)

`DatabaseSeeder` creates one account per role. It never runs `DemoUsersSeeder` in production. Every account uses the password `password`.

| Role | Email |
| --- | --- |
| Super Administrator | admin@nebostage.test |
| Operations Manager | operations@nebostage.test |
| Inventory Manager | inventory@nebostage.test |
| Production Manager | production@nebostage.test |
| Finance / Commercial | finance@nebostage.test |
| Technician | technician@nebostage.test |
| Crew | crew@nebostage.test |
| Viewer | viewer@nebostage.test |

## Production setup

```bash
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force   # roles, permissions and all reference data; safe on every deploy
php artisan nebo:create-admin --email=you@company.com           # prompts for name and password
```

Then sign in. Go to **Settings** and enter the real company email and phone (the default email is a placeholder). Set `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` and real `MAIL_*` values. Serve the site over HTTPS, which the PWA's service worker requires.

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan test` | Run the test suite |
| `vendor/bin/pint` | Format code (CI runs `pint --test`) |
| `php artisan nebo:create-admin` | Create or promote a Super Administrator |
| `php artisan db:seed --class=ReferenceDataSeeder` | Sync permissions and add missing roles, statuses, services and option lists |
| `php scripts/generate-icons.php` | Regenerate the PWA icons from the logo geometry |
| `php artisan nebo:maintenance-reminders` | Send the maintenance-due digest. Run it once a day from cron (or run `php artisan schedule:run` from cron at least hourly; it is scheduled for 07:00 Lagos) |
| `php artisan nebo:expire-quotations` | Mark sent quotations past their validity date as expired (scheduled daily at 00:15 Lagos) |

## What works today (Phases 1–9)

- Sign-in and sign-out, password reset by email, login throttling, deactivated accounts blocked immediately.
- Users and roles with granular permissions. Nobody can grant access they do not hold themselves, and the last super administrator is protected.
- Audit log that cannot be changed through the app, with before/after values, user, IP and device.
- In-app notifications, global search, settings, configurable reference numbers.
- Branded error pages, security headers, and an installable PWA with an offline page.
- Public Event Production Request form with uploads, confirmation and customer tracking; an internal requests module with workflow, timeline, assignment, notes and documents; a services catalogue.
- Events: conversion from requests, a workspace with team, documents, timeline, notes and financials, staff and crew with a double-booking guard, and a month/week/day production calendar.
- Inventory: equipment catalogue (table/grid, filters), serialized assets with QR labels and full history, quantity stock per location with quarantine, write-offs and stock counts, a stock movements ledger, and setup for categories, locations, statuses and option lists.
- Equipment for events: requirements with shortages, clashes and alternatives, date-aware availability (with an availability grid), race-safe allocation, load lists with a printable load sheet and dispatch, and check-in with missing/damage handling.
- Maintenance: fault reports and planned servicing with scheduled windows that block allocation, recurring schedules with reminders, inspections and damage reports with photos, and condition history.
- Logistics: a fleet register with paperwork dates, trips to venues and back with manifests, vehicle and driver clash checks, departure and delivery tracking that updates where each unit is, and a driver view.
- Commercial: customer profiles with duplicate merging, production packages, and quotations with approval, server-computed totals, a printable copy and online acceptance by the customer.
- Reports with charts and CSV export: utilisation, events and pipeline, maintenance, logistics, inventory and commercial; dashboard trends.
- Every tap shows feedback and can't be submitted twice.

The sidebar lists every module still to come under **Coming next**. Those modules are not active yet; see the roadmap.
