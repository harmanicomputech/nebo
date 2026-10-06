# Nebo Stage — Architecture

Nebo Stage is a nationwide (Nigeria) event production and technical services company. This application is its operations platform: a public event production request portal, and an internal system covering inventory, availability, allocation, logistics, maintenance, CRM, quotations and reporting.

This document is the Phase 0 output: what existed, the decisions taken, the target architecture, the module dependency map and the risks. The database design is in [DATABASE.md](DATABASE.md) and the phased plan in [ROADMAP.md](ROADMAP.md).

---

## 1. Discovery findings (Phase 0)

| Question | Finding |
| --- | --- |
| Existing project | `harmanicomputech/nebo` was an **empty repository**. There was no code to preserve. |
| Related code | The session also had `harmanicomputech/claude` (Election Shield, a Laravel 13 USSD election app). It is a different product with a live deadline and was **not modified**. Patterns proven there and worth carrying over are listed below. |
| Framework | Laravel **13.34** (fresh skeleton), PHP **^8.3**. The lockfile resolves Symfony 7.4, so it runs on PHP 8.3 *and* 8.4 (shared hosts often lag). |
| Frontend | Blade + Tailwind CSS v4 + Alpine.js, built with Vite 8. Icons: Lucide via `mallardduck/blade-lucide-icons` (server-rendered SVG, no icon font, works offline). |
| Database | MySQL 8 / MariaDB 10.6+ in production; SQLite in-memory for tests. Every migration must run on both. |
| Authentication | None existed. Built in Phase 1 (session auth, password reset, throttling). |
| Reusable UI | None. A design system of Blade components is built in Phase 1 (`resources/views/components/ui`). |

Patterns carried over from Election Shield (re-implemented, not shared code):

- An append-only **audit log** that never breaks the action being recorded.
- A cached key–value **settings** store editable from the console.
- **Thin controllers**, business logic in `app/Services`, side effects queued.
- Deployability on **shared hosting** (no terminal, no per-minute cron) is kept in mind: prebuilt assets, database queue, no daemons required for core features.

## 2. Key decisions

Each decision is recorded with its reason so it can be revisited deliberately.

| # | Decision | Why |
| --- | --- | --- |
| D1 | Server-rendered **Blade + Alpine** rather than an SPA. | The public portal must be fast and indexable; the internal app is form/table heavy; a single deployable with no Node runtime suits the hosting. API-readiness comes from the service layer (D4), not from the UI. |
| D2 | **spatie/laravel-permission** for RBAC. Permissions are code-defined (`App\Support\Permissions\PermissionCatalog`); roles are data. | Permissions map 1:1 to checks in policies and routes, so they belong with the code. Roles must be editable by administrators. A seeder syncs the catalog idempotently. |
| D3 | **Super Administrator** is granted everything through `Gate::before`, and is a protected system role. | New permissions never need to be granted to it by hand; the last active super admin cannot be removed or deactivated. |
| D4 | Business logic lives in **services** (`app/Services`), validation in **Form Requests**, authorization in **Policies**. Controllers orchestrate only. | Same logic is reused by the web UI, future `/api/v1` (Sanctum), console commands and jobs. |
| D5 | **Users ≠ staff.** `users` are login accounts. `staff` (Phase 4) are people who work events and may or may not log in (`staff.user_id` nullable). | Crew and drivers need to be scheduled without being given accounts. |
| D6 | **Two inventory tracking modes on one catalogue.** `equipment` is the catalogue item (e.g. "Robe MegaPointe"). `tracking_mode = serialized` items have rows in `equipment_assets` (one per physical unit); `tracking_mode = bulk` items have quantities in `stock_levels` per location. | Satisfies both "ML-001 is at the client site" and "we have 1,200 m of 16 A cable" without forcing one model onto the other. |
| D7 | **Asset statuses are a database table with behaviour flags** (`is_allocatable`, `is_in_service`, `is_terminal`, …) and a stable `code`. Engine logic keys off flags and system codes, never off labels. System statuses cannot be deleted. | Admins can add statuses and rename labels without breaking the availability engine. |
| D8 | **Availability is computed from time-windowed holds**, not from a stock counter. Each event has a *hold window* = setup start → breakdown end (+ configurable turnaround buffer). Available(item, window) = in-service units − overlapping holds − maintenance windows − units in non-allocatable status. | The brief requires conflict detection across overlapping dates; a single quantity column cannot express that. |
| D9 | **Double allocation is prevented in the database transaction**, not just in validation: allocation locks the catalogue row (`SELECT … FOR UPDATE`), re-checks overlap, then writes. Serialized assets additionally have a per-asset overlap check. | MySQL cannot express a range-exclusion constraint; serialising writes per equipment item is the reliable alternative. |
| D10 | **Every inventory movement writes an `inventory_transactions` ledger row** (who, what, from/to location, from/to status, qty, reference). Current state is denormalised on the asset for speed; the ledger is the history. | "How did this asset get here?" must always be answerable. |
| D11 | **Workflow statuses** (requests, events, quotations, load lists) are PHP backed enums with an explicit transition map, and every change writes a `status_changes` row (polymorphic: from, to, user, note, time). | Transitions are business rules that need tests; history is required by the brief. Labels are translatable; the set itself changes only with code. |
| D12 | **Configurable option lists** (event types, budget ranges, condition grades, maintenance types, staff roles) live in a `lookups` table (`group`, `key`, `label`, `sort`, `is_active`, `meta`). Services and equipment categories get dedicated tables because other records relate to them. | No hard-coded business values; one admin screen manages them all. |
| D13 | **Reference numbers** (`NEBO-REQ-2026-00001`) come from `ReferenceGenerator`, backed by a `sequences` table locked per key/period. Formats are settings (`{PREFIX}-{YYYY}-{SEQ:5}`). | Gap-free-enough, concurrency-safe, configurable. |
| D14 | **Money is stored as integer kobo** (`bigint`), currency NGN. | No floating-point rounding in quotations and reports. |
| D15 | **Files are private** (`storage/app/private`), stored under random names, served only through an authorising controller. A polymorphic `documents` table links them to any record. Uploads are validated by extension *and* MIME, size-capped. | Customer drawings and contracts must never be web-reachable by URL guessing. |
| D16 | **Soft deletes / archiving** for master data (equipment, assets, customers, users, categories, locations). Historical rows (allocations, ledger, audit) are never deleted. Retiring an asset is a status, not a delete. | Production history must survive. |
| D17 | **Notifications** use Laravel's notification system with the `database` channel (in-app) first. Each notification's `via()` goes through `NotificationChannels::for($user, $type)`, so mail/SMS/WhatsApp are added by configuration later. | The brief asks for in-app first, multi-channel later. |
| D18 | **Public portal and internal app are separated** by route group, layout, middleware and controllers (`App\Http\Controllers\Public\*` vs `App\Http\Controllers\Internal\*`). Public controllers never load inventory models. Public request tracking uses reference + a random token, never sequential IDs. | Requirement 45. |
| D19 | **PWA**: web manifest, service worker and offline page. The service worker caches only static, fingerprinted assets and the offline page; **authenticated HTML and API responses are never cached** (shared/lost devices must not leak operational data). | Installable on phones/tablets for crew and technicians, safely. |
| D20 | Timezone **Africa/Lagos** for display; timestamps stored in UTC. | Single-country operation today; correct if branches span zones later. |
| D21 | `Gate::before` grants Super Administrator only **permission abilities** (`module.action`); policy methods still run for them. | Found in Phase 1 testing: a blanket bypass let a super admin past structural policy rules (deleting a system role, deactivating themselves). The service layer still blocked it, but each rule should hold at every layer. |
| D22 | Fonts are **self-hosted from npm** (`@fontsource-variable/*`) and bundled by Vite. | No third-party request at build or run time, and the fonts work offline in the PWA. |

## 3. Target architecture

```
                         ┌───────────────────────────────┐
  Customers ───────────▶ │ Public portal  (/ , /request) │  layouts/public, Public\* controllers
                         └──────────────┬────────────────┘
                                        │ RequestIntakeService (validated, throttled)
                                        ▼
 ┌──────────────────────────────────────────────────────────────────────────────┐
 │ Internal app  (/app/*)   auth + active-user + permission middleware + policies│
 │                                                                              │
 │  Controllers (thin) ─▶ Form Requests ─▶ Services ─▶ Models / DB transactions │
 │                                           │                                  │
 │        AvailabilityService ◀──────────────┤                                  │
 │        AllocationService, ReturnService   │──▶ InventoryLedger (ledger rows) │
 │        RequestWorkflow, QuotationService  │──▶ Audit (audit_logs)            │
 │        ReferenceGenerator, Settings       │──▶ Notifications (db → mail/SMS) │
 └──────────────────────────────────────────────────────────────────────────────┘
                 │ queue (database driver)            │ private storage (documents)
                 ▼                                     ▼
            Jobs: notifications, exports,        storage/app/private/…
            maintenance reminders
```

Directory conventions:

```
app/
  Enums/                     workflow statuses, tracking modes (backed enums with labels/transitions)
  Http/Controllers/Public/   public portal
  Http/Controllers/Internal/ internal app (one controller per resource)
  Http/Controllers/Auth/     login, logout, password reset
  Http/Requests/             form requests (all server-side validation)
  Http/Middleware/           EnsureUserIsActive, SecurityHeaders
  Models/                    Eloquent models (relationships, casts, scopes; no workflows)
  Notifications/             in-app (+ future channels)
  Policies/                  one per model
  Services/                  business logic (availability, allocation, workflow, references)
  Support/                   Audit, Settings, PermissionCatalog, Navigation, Search
resources/views/
  components/ui/             design-system components (button, badge, card, modal, drawer, …)
  layouts/                   app (internal), public, auth, error
  internal/ public/ auth/ errors/
```

### Authorization layers

1. Route middleware: `auth`, `active` (deactivated users are logged out), `can:<permission>` on module groups.
2. Policies on every model for record-level rules (e.g. a technician sees only assigned maintenance).
3. Blade `@can` hides actions the user cannot take — never the only line of defence.
4. Permission names are `module.action`, e.g. `inventory.view`, `allocations.manage`, `quotations.approve`, `financial.view` (money visibility is its own permission so crew can see an event without its value).

### Security baseline

CSRF on every form; Form Request validation; Blade escaping (no `{!! !!}` on user data); mass-assignment via explicit `#[Fillable]`; bcrypt/argon passwords; login throttling (5/min per email+IP) and public-form throttling; session regeneration on login; encrypted, `HttpOnly`, `SameSite=Lax`, `Secure` (in production) session cookie; security headers middleware (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS); custom error pages with no stack traces when `APP_DEBUG=false`; secrets only in `.env`.

## 4. Module dependency map

```
Foundation (auth, RBAC, settings, audit, notifications, references, lookups, documents)
   │
   ├── Inventory  (categories, locations, statuses, equipment, assets, stock, ledger)
   │      │
   │      ├── Maintenance & condition ─────────────┐
   │      │                                         │ (maintenance windows block availability)
   │      └──────────────┐                          │
   │                     ▼                          ▼
   ├── CRM (customers) ─▶ Public requests ─▶ Events & production ─▶ Availability engine
   │                           │                    │                     │
   │                           │                    ├── Team / staff      ▼
   │                           │                    │              Allocation ─▶ Load lists ─▶ Check-out ─▶ Return/check-in
   │                           │                    │                                                         │
   │                           ▼                    ▼                                                         ▼
   │                      Quotations ◀── Packages   Logistics ◀── Vehicles                          Damage / missing → Maintenance
   │
   └── Reports & dashboard (read models over everything above)
```

Hard dependencies (a module cannot ship before these):

| Module | Depends on |
| --- | --- |
| Inventory | Foundation |
| Public requests | Foundation (references, documents, notifications), CRM customer matching, services |
| Events | Requests (conversion), customers, lookups |
| Availability & allocation | Inventory, events, maintenance windows (stubbed as "none" until Phase 6, then plugged in) |
| Load lists, returns | Allocation |
| Maintenance | Inventory (and returns feed it) |
| Logistics | Events, vehicles, staff |
| Quotations | Customers, events/requests, services, packages, equipment |
| Reports | All of the above |

## 5. Risks and ambiguities (with the assumption taken)

| Risk / ambiguity | Assumption / mitigation |
| --- | --- |
| Hosting is unknown. | Build to run on shared hosting *and* a VPS: database queue and cache, prebuilt assets committed to the release package (not to git), no required daemons. Revisit if a VPS is confirmed (then: Redis, Horizon, supervisor). |
| PHP 8.3 vs 8.4 dependency drift. | The lockfile is generated on PHP 8.3; CI tests 8.3 and 8.4. |
| Concurrency of allocations (two planners at once). | D9 row locks + tests that simulate conflicting writes. MySQL InnoDB required (not MyISAM). |
| Bulk items have mixed condition (some cable damaged). | Bulk stock keeps quantity per **location and condition bucket** (`available`, `quarantine` for damaged/inspection); only `available` counts for allocation. |
| Kits / road cases containing assets. | Phase 5 adds `asset_containers` (an asset may be "inside" a case asset) so a case can be scanned and its contents moved together. Not in Phase 1–4. |
| Customer de-duplication (same phone, different company; shared office emails). | Match on normalised email first, then normalised phone (E.164 +234); on match, link to the existing customer and flag `needs_review` if the name/company differs, rather than auto-merging. |
| Event dates with gaps (e.g. Fri + Sun). | Hold window spans setup start → breakdown end continuously. Gaps can be modelled later as multiple windows per event. |
| "Viewed" quotation status needs a customer-facing link. | Phase 8 adds a signed public quotation link that records the first view. |
| Public form spam. | Throttling, honeypot field, minimum fill-time check; CAPTCHA (Turnstile) as a config option later. |
| Email deliverability on shared hosts. | Notifications are queued; in-app first; mail failures never fail the request submission. |
| Time zones across branches later. | Store UTC, display Africa/Lagos (configurable). |
| Large inventory lists. | Server-side pagination, indexed filters, eager loading; no client-side loading of whole tables. |
| CSP with Alpine.js (`unsafe-eval`). | Phase 10 evaluates Alpine's CSP build and a strict Content-Security-Policy. |
