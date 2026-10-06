# Nebo Stage — Implementation Roadmap

Each phase ends with the gate in §62 of the brief: tests pass, errors fixed, architecture and relationships reviewed, authorization checked, responsive UI checked, nothing earlier broken, documentation updated. A phase is only marked done when it meets the Definition of Done (§65).

| Phase | Scope | Status |
| --- | --- | --- |
| 0 — Discovery & architecture | Repo inspection, decisions, schema, dependency map, risks | **Done** — [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md) |
| 1 — Foundation | Auth (login, logout, throttling, password reset, deactivated users), RBAC (permission catalog, editable roles, users), settings, audit log, in-app notifications, reference generator, global search framework, design system, internal + public layouts, error pages, security headers, PWA | **Done** (see below) |
| 2 — Inventory | Categories (+sub), locations, asset statuses, lookups, equipment catalogue, serialized assets, bulk stock, ledger, equipment profile, table/grid views, filters, QR-ready identifiers | Next |
| 3 — Public booking | Public site, Event Production Request form, services, customer matching, document uploads, references, confirmation, tracking page, staff notification, request workflow + timeline | Planned |
| 4 — Events & production | Request → event conversion, event workspace (tabs), staff & team, production calendar | Planned |
| 5 — Availability & allocation | Availability service, requirements with shortages/conflicts/alternatives, allocation, load lists (+ print), check-out, return/check-in, missing/damage flags, availability calendar | Planned |
| 6 — Maintenance & condition | Maintenance records/schedules, inspections, damage reports with photos, condition history, availability integration, reminders | Planned |
| 7 — Logistics | Vehicles, trips, drivers, crew, dispatch/delivery/return tracking | Planned |
| 8 — Customers & commercial | CRM profile, quotations (+ PDF-ready), packages, quote workflow | Planned |
| 9 — Reporting | Inventory, utilisation, events, maintenance, commercial reports; dashboard charts | Planned |
| 10 — Hardening | Security/permission review, CSP, query/index review, performance, accessibility, mobile polish | Planned |

## Phase 1 deliverables

- **Auth:** `/login`, `/logout`, `/forgot-password`, `/reset-password`; 5 attempts/min per email+IP; session regenerated on login; deactivated users cannot log in and are logged out mid-session; `nebo:create-admin` command for the first account.
- **RBAC:** `PermissionCatalog` defines every permission for all planned modules; `RolesAndPermissionsSeeder` syncs it and seeds the 8 roles from the brief (editable afterwards); Super Administrator via `Gate::before`; role editor with a permission matrix; user management (create, edit, roles, activate/deactivate, reset password); safeguards for the last super admin and for self-lockout.
- **Settings:** company profile, contact details, reference formats, timezone; audited.
- **Audit log:** `Auditable` trait on models (create/update/delete with before/after, secrets redacted) + `Audit::record()` for actions; filterable viewer; immutable.
- **Notifications:** database channel, bell with unread count, list, mark read/all read, channel resolver for future mail/SMS/WhatsApp.
- **Reference generator:** `NEBO-REQ-{YYYY}-{SEQ:5}` style formats, concurrency-safe, yearly reset.
- **Search:** global search box with grouped results, permission-aware providers (users and roles now; each module registers its own provider).
- **UI:** design system components, sidebar (planned modules shown as "Planned"), top bar, breadcrumbs, mobile drawer, toasts, confirmation modals, empty states, skeletons; branded error pages (403/404/419/429/500/503).
- **PWA:** manifest, icons, service worker (static assets only), offline page, install prompt.

### Phase 1 gate (§62)

- 75 feature tests / 295 assertions pass on PHP 8.3 with SQLite. CI also runs PHP 8.4 and MySQL 8; the MySQL job had not run yet when Phase 1 was committed.
- Checked in Chromium at 1440px and 390px: every internal page has no horizontal scroll, the mobile drawer works, there are no console errors, and the service worker registers.
- Bugs found and fixed during the gate: setting defaults with dotted keys, super-admin bypass of policies (D21), audit context in tests, a table's hidden header overflowing the page on phones, the dashboard link showing as active on every page, and an empty contact email.
