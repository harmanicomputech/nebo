# Nebo Stage

Operations platform for Nebo Stage, a **nationwide** Nigerian event production company. Never describe it as Ilorin-based. It has a public portal (`/`) and an internal system (`/app`), and it is a PWA. Laravel 13 on PHP 8.3+, MySQL in production, SQLite in-memory for tests. Brand colours: `#CC1F1F` (`brand-600`) and `#1A1A1A` (`ink-900`).

Read `docs/ARCHITECTURE.md` (decisions D1–D59), `docs/DATABASE.md` and `docs/ROADMAP.md` before starting a new phase. Update them when a decision or the schema changes.

## Commands

- `php artisan test`: run the suite. CI runs it on PHP 8.3/8.4 with SQLite and on MySQL 8.
- `vendor/bin/pint`: format. CI runs `pint --test`.
- `npm run build`: build assets. Fonts come from `@fontsource` and are bundled; never add a font CDN.
- `php artisan migrate:fresh --seed`: reset local data with demo users (`admin@nebostage.test` / `password`).

## Conventions

- **Thin controllers.** Business rules belong in `app/Services`, validation in `app/Http/Requests`, and record-level authorization in `app/Policies`. Use `Internal\*` controllers for `/app` and `Public\*` controllers for the public site. Public controllers must never load internal models (inventory, staff, pricing).
- **Permissions** are `module.action` and are defined only in `App\Support\Permissions\PermissionCatalog`. After adding one, run `RolesAndPermissionsSeeder`. `Gate::before` grants Super Administrator every *permission* (an ability containing a dot), but policy methods still apply to them. Keep structural rules (for example "system roles can't be deleted") in policies or services.
- **Nobody grants access they don't hold.** `UserAdministration` and `RoleAdministration` enforce this. The last active super administrator cannot be removed.
- **Audit:** add `App\Support\Audit\Auditable` to models. For actions that aren't model diffs (status changes, allocation, sign-in), call `Audit::record()`. Audit logs are append-only. Passwords and tokens are never logged.
- **Settings:** use `App\Support\Settings`. Defaults live in `config('nebo.defaults')`. Keys contain dots, so never read them with dot-notation `config()`.
- **References** come from `ReferenceGenerator::next('request'|'event'|…)`. Formats are settings.
- **Notifications** extend `App\Notifications\NeboNotification`. They are in-app by default; channels come from `NotificationChannels`.
- **Inventory changes go through services.** `AssetService` (register, status, move, condition, archive), `StockService` (receive, transfer, quarantine/release, write off, count) and `EquipmentService` write the ledger in the same transaction. Never update an asset's status, location or condition, or a stock quantity, directly. Asset behaviour comes from status `group`/`is_allocatable`/`is_manual` and condition `meta`, never from labels (D23–D25).
- **Public portal:** `Public\*` controllers may read only `Service`, option lists, settings, and a customer's own `EventRequest` by its token. Never expose internal statuses, notes, staff or documents (D33). Public datetimes are Lagos time and are converted to UTC in the form request (D35).
- **Workflow history** goes in `status_changes` (`HasStatusHistory`). Use `notes` and `documents` (`HasNotesAndDocuments`) for any record; register new document owners in `DocumentController::OWNERS`. Uploads are validated with `UploadRules` (content-sniffed).
- **Events:** create and convert through `EventService`, change status through `EventWorkflow`, assign crew through `TeamService` (double-booking guard). Use `Event::scopeVisibleTo($user)` for any event list, because Crew and Technicians only see their assigned events (D40). The hold window is `setup_starts_at → breakdown_ends_at`; use `scopeOverlapping()` for clashes.
- **Equipment bookings** go through `AllocationService` (reserve, release, rewindow), `LoadListService` and `ReturnService`. Ask `AvailabilityService` for free units; never count allocations by hand. Anything that takes units out of service for a period (maintenance, repairs) implements `AvailabilityBlocker` and is registered in `AvailabilityService::BLOCKERS` (D43).
- **Maintenance** goes through `MaintenanceService` (report, schedule, start, complete, cancel), `MaintenanceScheduler` (schedules, opening jobs from them) and `InspectionService` (condition + photos + optional job). Use `MaintenanceRecord::scopeVisibleTo($user)` for job lists (technicians see their own). Condition reports are append-only (D50–D53).
- **Logistics:** plan and progress trips through `TripService` (clash checks, manifest rules, asset status on departure/arrival); vehicles through `VehicleService`. Use `LogisticsTrip::scopeVisibleTo($user)` for trip lists (drivers see their own). Don't name a column `notes` on a model with `HasNotesAndDocuments` (D54–D58).
- **Option lists** are read through `app(App\Support\Lookups::class)` (scoped, not static). Records store the lookup `key`. Add new groups to `Lookups::GROUPS`.
- **Strict models:** lazy loading is disabled outside production, so eager-load relations that views use.
- **Money** is stored as integer kobo. Times are stored in UTC and displayed with `App\Support\Format` (Africa/Lagos).
- **No hard-coded business lists.** Services, categories, locations, statuses and option lists belong in the database. Reference data is seeded idempotently by `ReferenceDataSeeder` (it calls each module's reference seeder and is safe in production; add new ones to it); demo data uses `DEMO-` SKUs.
- **Nothing fake.** A module that isn't built appears only under "Coming next" in `App\Support\Navigation` (pass `planned(...)`), never as a working link or button.
- **UI:** use the Blade components in `resources/views/components/ui` (button, badge, card, stat, page-header, input, select, textarea, modal, confirm, drawer, table, empty-state, skeleton) and the layouts `x-layouts.app|auth|public|error`. Wrap tables in `<x-ui.table>`, which scrolls on phones. Check every page at 390px wide with no horizontal scroll.
- **PWA:** `public/sw.js` caches only `/build`, icons and `/offline`. Never cache HTML or JSON. Bump `VERSION` when the caching logic changes.
- **Destructive actions** use `<x-ui.confirm>`. Every form and same-site link gets tap feedback and double-submit protection automatically (D59); add `data-no-busy` to links or forms that download a file instead of loading a page.
