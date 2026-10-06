# Nebo Stage — Implementation Roadmap

Each phase ends with the gate in §62 of the brief: tests pass, errors fixed, architecture and relationships reviewed, authorization checked, responsive UI checked, nothing earlier broken, documentation updated. A phase is only marked done when it meets the Definition of Done (§65).

| Phase | Scope | Status |
| --- | --- | --- |
| 0 — Discovery & architecture | Repo inspection, decisions, schema, dependency map, risks | **Done** — [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md) |
| 1 — Foundation | Auth (login, logout, throttling, password reset, deactivated users), RBAC (permission catalog, editable roles, users), settings, audit log, in-app notifications, reference generator, global search framework, design system, internal + public layouts, error pages, security headers, PWA | **Done** (see below) |
| 2 — Inventory | Categories (+sub), locations, asset statuses, lookups, equipment catalogue, serialized assets, bulk stock, ledger, equipment profile, table/grid views, filters, QR-ready identifiers | **Done** (see below) |
| 3 — Public booking | Public site, Event Production Request form, services, customer matching, document uploads, references, confirmation, tracking page, staff notification, request workflow + timeline | **Done** (see below) |
| 4 — Events & production | Request → event conversion, event workspace (tabs), staff & team, production calendar | **Done** (see below) |
| 5 — Availability & allocation | Availability service, requirements with shortages/conflicts/alternatives, allocation, load lists (+ print), check-out, return/check-in, missing/damage flags, availability calendar | **Done** (see below) |
| 6 — Maintenance & condition | Maintenance records/schedules, inspections, damage reports with photos, condition history, availability integration, reminders | **Done** (see below) |
| 7 — Logistics | Vehicles, trips, drivers, crew, dispatch/delivery/return tracking | **Done** (see below) |
| 8 — Customers & commercial | CRM profile, quotations (+ PDF-ready), packages, quote workflow | **Done** (see below) |
| 9 — Reporting | Inventory, utilisation, events, maintenance, commercial reports; dashboard charts | **Done** (see below) |
| 10 — Hardening | Security/permission review, CSP, query/index review, performance, accessibility, mobile polish | **Done** (see below) |

## Phase 1 deliverables

- **Auth:** `/login`, `/logout`, `/forgot-password`, `/reset-password`; 5 attempts/min per email+IP; session regenerated on login; deactivated users cannot log in and are logged out mid-session; `nebo:create-admin` command for the first account.
- **RBAC:** `PermissionCatalog` defines every permission for all planned modules; `RolesAndPermissionsSeeder` syncs it and seeds the 8 roles from the brief (editable afterwards); Super Administrator via `Gate::before`; role editor with a permission matrix; user management (create, edit, roles, activate/deactivate, reset password); safeguards for the last super admin and for self-lockout.
- **Settings:** company profile, contact details, reference formats, timezone; audited.
- **Audit log:** `Auditable` trait on models (create/update/delete with before/after, secrets redacted) + `Audit::record()` for actions; filterable viewer; immutable.
- **Notifications:** database channel, bell with unread count, list, mark read/all read, channel resolver for future mail/SMS/WhatsApp.
- **Reference generator:** `NEBO-REQ-{YYYY}-{SEQ:5}` style formats, concurrency-safe, yearly reset.
- **Search:** global search box with grouped results, permission-aware providers (users and roles now; each module registers its own provider).
- **UI:** design system components, sidebar (planned modules listed under a collapsed "Coming next" group, never clickable), top bar, breadcrumbs, mobile drawer, toasts, confirmation modals, empty states, skeletons; branded error pages (403/404/419/429/500/503).
- **PWA:** manifest, icons, service worker (static assets only), offline page, install prompt.

### Phase 1 gate (§62)

- 75 feature tests / 295 assertions pass on PHP 8.3 with SQLite. CI also runs PHP 8.4 and MySQL 8; the MySQL job had not run yet when Phase 1 was committed.
- Checked in Chromium at 1440px and 390px: every internal page has no horizontal scroll, the mobile drawer works, there are no console errors, and the service worker registers.
- Bugs found and fixed during the gate: setting defaults with dotted keys, super-admin bypass of policies (D21), audit context in tests, a table's hidden header overflowing the page on phones, the dashboard link showing as active on every page, and an empty contact email.

## Phase 2 deliverables

- **Catalogue:** equipment with category/subcategory, SKU, make, model, tracking mode (serialized or quantity), unit, asset prefix, image, replacement value, low-stock level. Table and grid views; search (including asset tag and serial); filters for category (with subcategories), tracking, availability (available / none / low), unit status, location, condition and manufacturer; sorting; pagination; archive and restore.
- **Serialized assets:** add one unit or many with generated tags (`ML-001`), serial, barcode, purchase, cost, value, supplier, warranty and next-maintenance details. Asset profile with QR code, current state, identity and purchase details, full history; actions to record condition, change status and move, plus archive and restore. Assets list across the fleet with filters.
- **Quantity stock:** receive, move between locations, quarantine and release, write off and stock count, all per location, never negative, each with a ledger entry.
- **Ledger:** every change is recorded with who, when, from/to status, location, condition, bucket and a note. Shown on the equipment and asset pages and as the stock movements page.
- **Setup:** categories and subcategories, locations (archive only when empty), statuses (rename any, add custom, system behaviour fixed), and option lists (conditions, location types, units) under Settings.
- **QR:** printable A4 label sheet; `/app/scan/{token}` opens the asset (sign-in required).
- **Dashboard and search:** fleet status by group, available-now count, low stock, maintenance due in 14 days, quarantined stock; global search covers equipment and assets.
- **Data:** an idempotent, production-safe reference seeder (the 12 categories from the brief with subcategories, 14 statuses, conditions, location types, units, 4 locations) and a demo seeder (18 `DEMO-` items, 103 units, opening stock and realistic states) that never runs in production.

### Phase 2 gate (§62)

- 115 tests / 584 assertions pass (Phase 1 plus 40 new inventory tests covering every rule above).
- Every new page renders in Chromium at 1440px and 390px with no horizontal scroll and no console errors. The stock drawer and asset modals were checked.
- Bugs found and fixed during the gate: an N+1 query on the equipment history tab (caught by strict lazy-loading), editing a quantity item failing validation, a submitted "allocatable" flag overriding the rule for custom statuses, a nav highlight pattern that would have matched every inventory page, ledger wording for first entries, subcategory icons, and the phone layout of the catalogue.
- Not yet verified: the MySQL CI job (no MySQL in the build sandbox).

## Phase 3 deliverables

- **Public form** (`/request`): every field from brief §8–14 in six sections with a progress rail, service cards from the database, event-type chips, conditional "Other" fields, drag-and-drop uploads when the customer has a plan, budget options from the database, server-side validation with errors next to each field, and duplicate and bot protection (D31).
- **Submission:** reference `NEBO-REQ-YYYY-NNNNN` (configurable), customer matched or created (D32), services, private documents, the first timeline entry, in-app notification to request managers, an optional email copy to configured addresses, and a confirmation email to the customer when mail is configured (D36). Confirmation page with reference and private tracking link.
- **Tracking** (`/track`): by link, or reference + email; shows five simple stages only (D33).
- **Internal requests:** list with Open/Won/Closed views, search and filters (status, assignee, service, event dates), counts; request page with all details, documents (upload, download, remove), internal notes, assignment, status changes along the allowed transitions (reason required to cancel or decline) and the full timeline; notifications on assignment and status change; audit entries.
- **Services catalogue** (Commercial › Services / Settings › Services): add, edit, hide from the form, disable. The public home page and form read from it.
- **Reusable foundations:** `status_changes`, `notes` and `documents` are polymorphic and are used by later modules.

### Phase 3 gate (§62)

- 138 tests / 757 assertions pass (23 new).
- A real Chromium submission at 390px worked end to end: request, file, services, and 09:00 Lagos stored as 08:00 UTC. Every new page has no horizontal scroll at 390px and no console errors.
- Fixed during the gate: MIME checks now sniff the bytes with finfo instead of trusting the framework's guess, which a renamed PHP file could pass. Also fixed: the timezone of datetime inputs, the old-input redisplay of converted datetimes, settings tabs shown to people without access, and a navigation path for Finance to reach Services.

## Phase 4 deliverables

- **Events:** create directly or convert a won request (D38). Reference `NEBO-EVT-YYYY-NNNNN`. Schedule (setup → show → breakdown, Lagos time), type, venue, services, requirements, project and production managers, and budget (needs `financial.view`). Status workflow with history (D37); archive and restore.
- **Event workspace:** Overview, Production requirements, Team, Documents, Timeline, Notes and Financial tabs work. Equipment and Allocation followed in Phase 5, and Logistics in Phase 7.
- **Staff & crew:** profiles with role, contacts and an optional login link; upcoming and past assignments; crew double-booking guard with audited override (D39); in-app notification when a linked user is added to a team.
- **Production calendar:** month, week and day views with Setup / Show / Breakdown per day (D41).
- **Access:** Crew and Technicians see only their assigned events, in lists, calendar, search and dashboard (D40).
- **Dashboard and search:** today's events with their phase, upcoming events, counts for the week and month; global search covers events and staff.

### Phase 4 gate (§62)

- 150 tests / 849 assertions pass (12 new event tests).
- Every new page renders at 1440px and 390px with no horizontal scroll and no console errors, checked as admin and as Crew.
- Fixed during the gate: request conversion passed `services` into the event's attributes (caught by strict mass-assignment protection), a stray extra week in the month view, unreadable month cells on phones, a Blade slot inside `@can`, and stale dashboard copy.

## Phase 5 deliverables

- **Availability engine** (D43–D45): free units per item for any window, net of overlapping bookings, unserviceable units and (from Phase 6) maintenance blocks; configurable turnaround buffer; race-safe allocation.
- **Event equipment:** requirements per item with required / allocated / free / short, the events causing a clash, unserviceable units and alternatives from the same category family. Allocate by picking units, auto-picking or by quantity for bulk stock; release with a reason. Changing event dates re-checks every held unit.
- **Load-out:** a load list per event with Pending → Picked → Loaded → Checked per line, mark-all, case labels, a printable load sheet with signatures, and dispatch, which checks everything out. Load lists overview at `/app/load-lists`.
- **Check-in:** per-unit outcomes and per-line counts for bulk, partial returns, ledger entries, Lost / damaged / quarantine handling and a notification to inventory managers (D49).
- **Availability grid** at `/app/availability`: 14 days × items with free/total per day, by category, item or search.
- **Elsewhere:** the asset page lists its bookings, the dashboard shows deployments in the next 7 days, items out, overdue returns and kit in transit, and the calendar flags events whose equipment isn't fully allocated.

### Phase 5 gate (§62)

- 173 tests / 953 assertions pass (23 new: availability, allocation, load-out and returns, including concurrency-style re-checks, re-windowing and cancel/complete rules).
- Every new page renders at 1440px and 390px with no horizontal scroll and no console errors; a real allocation, load-list update and check-in were run in Chromium.
- Fixed during the gate: lazy loads in asset-status sync, the load list, returns and the requirement analyzer (caught by strict mode), an ambiguous `status` column in the load-lists filter, invalid markup (a block inside a paragraph) in the allocated-units list, and bulk load-list lines without a unit.

## Phase 6 deliverables

- **Maintenance jobs** (`/app/maintenance`, `NEBO-MNT-YYYY-NNNNN`): log a fault or planned service for a unit with type, priority, issue, technician and an optional window; schedule, start, complete (work done, parts, cost, condition after, next due) or cancel with a reason. Photos and documents, internal notes and a timeline on every job. Views for open jobs, due schedules, all schedules and closed jobs, with search and filters, plus counts for open, in-progress, high/urgent and overdue.
- **Availability integration** (D50): scheduled windows block allocation and event date changes; maintenance can't be booked over an allocation; units at an event can't be worked on until they're back.
- **Schedules and reminders** (D52): per-unit recurring schedules (or every unit of an item at once), editing and pausing, "open job" from a due schedule, automatic advancing on completion, and a daily reminder digest with a configurable lead time (Settings › Maintenance).
- **Inspections and damage reports** (D53): from the asset page, with condition, notes, up to five photos (content-checked) and an optional follow-up job. Condition history on the asset page with photo thumbnails.
- **Check-in follow-up:** damaged, needs-inspection and needs-maintenance returns open a job and record condition history.
- **Elsewhere:** maintenance windows on the production calendar, open jobs and a link to due schedules on the dashboard, maintenance in global search, technicians notified when a job is assigned to them and seeing only their own jobs.

### Phase 6 gate (§62)

- 185 tests / 1,051 assertions pass (12 new maintenance tests).
- Every new page renders at 1440px and 390px with no horizontal scroll and no console errors, checked as admin and as Technician; an inspection with a photo and follow-up job, and completing a job, were run in Chromium.
- Fixed during the gate: a lazy load when updating a schedule (caught by strict mode), and stale "coming in Phase 5/6" copy on the event and calendar pages.

## Phase 7 deliverables

- **Fleet** (`/app/logistics/vehicles`): vehicles with type, capacity, payload, regular driver, base, insurance and roadworthiness dates (flagged 30 days ahead), status, documents and notes; archive and restore.
- **Trips** (`/app/logistics`, `NEBO-TRP-YYYY-NNNNN`): plan from an event's Logistics tab (to the venue or back to base, with route, times and manifest prefilled) or as a transfer; vehicle, driver, crew, manifest and instructions; Upcoming / Today / On the road / Past views with filters and counts.
- **Rules** (D54–D56): vehicle clashes and out-of-service vehicles refused, driver and crew double-booking only with an audited reason, manifest limited to the event's own kit and to one trip per direction, departure only with a vehicle, a driver and dispatched kit.
- **Tracking:** Loading → In Transit → Arrived with times and who received it; units move to In Transit and then On Site or Deployed, with ledger entries; delivery notes and photos on the trip; timeline and notes.
- **Drivers** (D57): Crew see and update only the trips they drive or crew, on the logistics page, the dashboard ("Your trips"), the calendar and search; they're notified when added to a trip.
- **Elsewhere:** trips on the production calendar, trips today and tomorrow on the dashboard, trips and vehicles in global search, Logistics and Fleet in the navigation.

### Phase 7 gate (§62)

- 195 tests / 1,140 assertions pass (10 new logistics tests; two earlier tests updated for the now-live Logistics tab and Crew's new permission).
- Every new page renders at 1440px and 390px with no horizontal scroll and no console errors, checked as admin and as Crew; editing a trip, a refused departure (kit not dispatched) and planning a trip were run in Chromium.
- Fixed during the gate: `notes` columns on trips and vehicles shadowed the notes relation (renamed, D58), and breadcrumbs that dropped their first item.

## Tap feedback (between Phases 7 and 8)

- Every button and link shows a pressed state on touch screens; the tapped button shows a spinner and further taps are ignored until the next page loads; a progress bar runs at the top (D59). Verified in a phone emulator with a slowed server: three taps sent one request, and the tapped button's value still reached the server.

## Phase 8 deliverables

- **Customers** (`/app/customers`): list with search, type filter, "to check" filter and sorting (by accepted value for `financial.view`); profile with contact details, requests, events, quotations, accepted and pending value, notes and documents; add and edit; likely duplicates shown for flagged profiles, "mark checked" and **merge** (D63).
- **Quotations** (`/app/quotations`): start from a request (prefilled with the requested services), an event, a customer or a package; line editor with sections, catalogue picker (equipment day rates, services), live preview; server-computed totals with discount and VAT; approve and send, customer link with copy button and "opened" tracking, email to the customer when mail is set up; record an answer by phone; revise, duplicate, cancel; printable / PDF-ready document; notes, documents and timeline. Request and event pages list their quotations; the event's Financial tab compares accepted value with the budget.
- **Customer page** `/q/{token}` (D62): branded quotation that reads well on a phone, PDF download through the browser, accept (name + tick) or decline.
- **Packages** (`/app/packages`): priced bundles with lines, event type, hide/show, archive; "quote with it".
- **Settings:** quotation validity, VAT and standard terms; reference format for quotations. Equipment gets a day rate (cost-permission only).
- **Elsewhere:** customers and quotations in global search, Customers / Quotations / Packages in the navigation, a daily expiry job.

### Phase 8 gate (§62)

- 204 tests / 1,224 assertions pass (9 new commercial tests).
- Every new page renders at 1440px and 390px with no horizontal scroll and no console errors. In Chromium a quotation was built with the line editor (₦387,000 preview matched the saved total), approved and sent, then accepted on a phone through the customer link.
- Fixed during the gate: a discount entered before any lines was lost (now kept and applied up to the subtotal), package lines passing the section as an enum, the copy-link button's script, an unlabeled catalogue picker, and line tables that scrolled sideways on phones (now stacked cards).

## Phase 9 deliverables

- **Reports** (`/app/reports`): equipment utilisation, events & request pipeline, maintenance, logistics, inventory and commercial (financial permission), each with summary figures, charts, a table view, period presets or a custom range, print and CSV export (D65).
- **Charts** (D64): column (single and stacked) and ranked-bar components, server-rendered, with validated colour-blind-safe colours, legends, tooltips on hover and keyboard focus, and a table view.
- **Dashboard:** a Trends panel (events three months back and ahead; accepted quotation value for the last six months with `financial.view`) replaces the module roadmap — every module is now live and the sidebar's "Coming next" group is gone.
- **Responsive fix everywhere:** stat tiles stack their icon above the figure on phones and long figures wrap; cards can shrink inside grids.

### Phase 9 gate (§62)

- 209 tests / 1,264 assertions pass (5 new: utilisation maths, report access, CSV export with formula neutralising, dashboard trends, chart scales; the sidebar test now expects no "Coming next").
- Every report and the dashboard render at 1440px and 390px with no horizontal scroll and no console errors; a tooltip and a CSV download were exercised in Chromium (the export link doesn't stay busy).
- Fixed during the gate: long naira figures overflowing stat tiles on phones, and grid cards that couldn't shrink below their content.

## Phase 10 deliverables (hardening)

- **Access audit** (`AccessAuditTest`): every `/app` route sends guests to sign-in, and a signed-in user with no module permissions gets 403 on every parameterless page except their own (dashboard, profile, notifications, search). All passed with no fixes needed.
- **Content-Security-Policy** and inline-code ban (D66): the audit found three inline handlers (category archive confirm, equipment unit filters) that the policy would have blocked; they now use Alpine, and print/reload/back buttons use `data-action`.
- **Accessibility:** axe-core (WCAG 2 A/AA) across 60 internal and 8 public pages: zero serious or critical issues after fixes. Fixed: low-contrast greys (the lightest text grey is now 4.5:1 on white and on light grey; dark surfaces use translucent white), red text on black (lighter red on dark), faded totals in the availability grid, chart columns that were focusable inside a hidden region (now labelled images), a link distinguished only by colour, and a malformed definition list.
- **Performance:** query counts measured on 26 pages with the demo data. The availability grid went from 180 to 23 queries (blocker windows and bulk stock fetched once instead of per item per day); the rest stay under about 40 queries and 80 ms locally. New indexes for report, dashboard and notification-bell filters. Every migration rolls back cleanly (full reset and re-run).
- **Production readiness** (D67): `nebo:check-production`, https link forcing, a scheduled queue drain for hosts without a worker, long-lived caching for hashed assets, denial of dotfiles, and `docs/DEPLOYMENT.md`.
- **Mobile:** every page checked at 390px (signed in and public): no horizontal scroll, no console errors, no failed requests; tap feedback on every control (D59).

### Phase 10 gate (§62)

- 213 tests / 1,903 assertions pass on PHP 8.3 with SQLite (4 new: access audit ×2, CSP/inline-code scan, production check). MySQL 8 CI is configured but has not been run in this environment.
- Crawled in Chromium at 1440px and 390px: all 68 pages load, no horizontal scroll, no JS errors, no serious or critical accessibility violations.

All ten phases of the brief are now delivered.

## After phase 10: demo data and no-terminal hosting

- **Demo data:** `HistoryDemoSeeder` adds a year of business through the real services (24 completed productions with quotes, load lists, trips, check-ins, damage and repairs; lost deals; a live pipeline), so every report and screen has data. It runs in batches so the web installer can load it.
- **DirectAdmin / cPanel package (D69):** upload zip, browser installer, Settings → System for health checks and updates, background jobs without cron (`nebo:tick`, `NEBO_WEB_CRON`).
- **MySQL verified:** full migrate, seed, rollback and the whole test suite run on MariaDB 10.11. This found an index name longer than MySQL's 64-character limit (now named explicitly) and a test that assumed auto-increment ids restart.
- **Phone app shell (D70):** mobile-first layout below 1024px with a bottom tab bar (four tabs chosen by role, plus More), a dark top bar with back navigation, full-screen search, card-style table rows, collapsible filters and bottom-sheet dialogs; desktop unchanged. Crawled 84 admin pages and the crew, technician, finance and viewer views at 390px (no horizontal scroll, no script errors); fixed an equipment link shown to roles without inventory access.
