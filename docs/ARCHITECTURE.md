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
| D7 | **Asset statuses are a database table with behaviour flags** (`group`, `is_allocatable`, `is_manual`; see D23) and a stable `code`. Engine logic keys off flags and system codes, never off labels. System statuses cannot be deleted. | Admins can add statuses and rename labels without breaking the availability engine. |
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
| D23 | Every asset status belongs to one of eight fixed **groups** (`App\Enums\AssetStatusGroup`: available, committed, out, attention, damaged, lost, retired, unavailable). Dashboards and reports count by group. `is_allocatable` says whether a unit can be allocated; `is_manual = false` marks the statuses that belong to the allocation engine (reserved, allocated, checked out, in transit, deployed, on site). | Admins can add and rename statuses freely, but the code never depends on labels or codes it doesn't own. Only "available"-group statuses can be allocatable. |
| D24 | **Engine-managed statuses can't be touched by hand**: staff can't set them, and an asset in one can't be changed, moved or archived manually. Moving into or out of lost/retired needs `inventory.archive`. | Stops a manual edit from silently breaking an allocation (Phase 5) or quietly writing off kit. |
| D25 | **Condition grades are lookups with behaviour in `meta`**: `blocks_allocation` takes the unit out of availability; `sets_status` moves an in-service unit to that status when the grade is recorded (damaged → Damaged, requires inspection → Under Inspection). | Damaged kit can't be allocated by mistake, and the rule is configurable. |
| D26 | **Bulk stock has two buckets per location**: `available` and `quarantine` (damaged or awaiting inspection). Only `available` counts. Write-offs and stock counts require a reason; stock can never go negative (row locks + checks). | Mixed-condition cable stock without serialising every cable. |
| D27 | **Asset tags** come from the shared `sequences` counter per prefix (`ML-001`) and skip tags typed by hand. Each asset also has an opaque **ULID `qr_token`**; QR labels encode `/app/scan/{token}`, which needs sign-in. QR codes are rendered as SVG by bacon/bacon-qr-code (pure PHP). | Labels leak nothing if photographed, tags stay unique, and it runs on shared hosting without Imagick. |
| D28 | **Uploaded images are decoded and re-encoded with GD** (max 1600px, WebP), stored on the private disk and served through an authorising route. | Strips EXIF/GPS, rejects disguised files, keeps pages light. |
| D29 | **"Available now"** (Phase 2) = allocatable status + non-blocking condition (serialized) or the available bucket (bulk), computed in SQL (`Equipment::availableUnitsSql()`). Phase 5 adds date-window holds on top of this; it does not replace it. | Filtering and sorting by availability in the database, with no full-fleet loads. |
| D30 | Lookups are read through `App\Support\Lookups`, a **container-scoped** memo, not a static cache. | Static caches go stale across tests and long-running workers (and on MySQL, auto-increment IDs don't roll back between tests). |
| D31 | **Public form protection without a CAPTCHA**: per-IP throttling (5/min, 20/h), a honeypot field, an encrypted form-start time (≥3 s, ≤24 h), and a one-time `submission_key` that makes resubmits idempotent. | Stops scripts and double clicks without friction for real customers; Turnstile can be added later if spam gets through. |
| D32 | **Customer matching**: normalised email, then E.164 phone (`App\Support\PhoneNumber`, Nigerian formats). A match with a different contact name or company sets `needs_review`; nothing is overwritten or auto-merged. | Returning customers aren't duplicated, and shared office emails/phones don't silently merge different people. |
| D33 | **Customers see 5 public stages** (`RequestStatus::publicStage()`), never the 15 internal statuses, notes, staff names or documents. Tracking uses an unguessable ULID token, or reference + email, throttled. | Requirement 45 and §49 (no internal terminology). |
| D34 | **Uploads are checked by content**: an extension allowlist plus the MIME type sniffed from the bytes with finfo, or the file signature for DWG/DXF. Files are stored under random names; images preview inline, everything else downloads, with `nosniff` and a sandboxing CSP. | A script renamed `.pdf` is rejected, whatever the client claims. |
| D35 | **Form datetimes are entered in Lagos time and converted to UTC** in the form request before validation and storage. | Avoids an hour's drift in setup times (D20). |
| D36 | **Email is sent only when a real mailer is configured** (not `log`/`array`), queued, and failures never lose the request. In-app notifications go to users with `requests.manage`. | §15: "send confirmation when email functionality is configured". |
| D37 | **Event lifecycle**: Planning → Confirmed → In Preparation → In Progress → Completed, plus On Hold and Cancelled (reasons required). Completed and Cancelled are read-only except notes. `EventStatus::holdsResources()` decides which events hold equipment and crew. | Explicit, testable transitions (D11); Phase 5 availability counts only active events. |
| D38 | **Request → event conversion** is allowed once, only for won requests; it copies customer, type, services, requirements and documents, and an Approved request moves to Production Scheduled. | One source of truth per production, with no lost history. |
| D39 | **Crew double-booking is blocked** across overlapping hold windows of active events, unless a manager overrides it with a reason, which is recorded in the audit log. | Brief §54 (no invalid scheduling), with room for real-world exceptions. |
| D40 | **Assigned-only access**: `events.view_assigned` (Crew, Technician) sees events where the user is the project manager, the production manager, or on the team through their staff profile. It is enforced in `EventPolicy` and in `Event::scopeVisibleTo()` for lists, the calendar and search. | Crew see their own work only. |
| D41 | **The calendar is built by `ProductionCalendar`** in Lagos time; each event shows on every day of its hold window labelled Setup / Show / Breakdown (text, not only colour). On phones, month cells show a count and phase bars that link to the day view. | Readable and accessible on every screen; Phases 5 and 6 add entry sources. |
| D42 | **Navigation items accept a permission or a closure**, so policy-based visibility (events for assigned-only users) needs no special cases. | Keeps `Navigation` declarative. |
