# Nebo Stage — Database Design

Conventions: `bigint` auto-increment primary keys; foreign keys with explicit `ON DELETE` behaviour (`restrict` for history, `cascade` only for owned child rows, `set null` for optional links); `timestamps` everywhere; `softDeletes` on master data; money as **integer kobo** (`*_amount` / `*_kobo` bigint); UTC timestamps. Tables marked **(P1)** exist after Phase 1; the rest are the target design for later phases and may be refined when built (changes are recorded here).

## Entity relationship overview

```mermaid
erDiagram
    users ||--o{ audit_logs : "acts in"
    users }o--o{ roles : "model_has_roles"
    roles }o--o{ permissions : "role_has_permissions"
    users ||--o| staff : "may be"

    customers ||--o{ event_requests : submits
    customers ||--o{ events : books
    customers ||--o{ quotations : receives
    event_requests ||--o| events : "converts to"
    event_requests }o--o{ services : "requests (event_request_services)"
    events }o--o{ services : "event_services"
    events ||--o{ equipment_requirements : needs
    events ||--o{ equipment_allocations : holds
    events ||--o{ event_staff : staffed_by
    events ||--o{ load_lists : "loads out"
    events ||--o{ logistics_trips : moves
    events ||--o{ quotations : priced_by

    equipment_categories ||--o{ equipment_categories : "parent of"
    equipment_categories ||--o{ equipment : groups
    equipment ||--o{ equipment_assets : "serialized units"
    equipment ||--o{ stock_levels : "bulk quantities"
    locations ||--o{ stock_levels : stores
    locations ||--o{ equipment_assets : "current location"
    asset_statuses ||--o{ equipment_assets : "current status"
    equipment_requirements ||--o{ equipment_allocations : "fulfilled by"
    equipment_assets ||--o{ equipment_allocations : "allocated as"
    equipment_assets ||--o{ inventory_transactions : ledger
    equipment ||--o{ inventory_transactions : ledger
    equipment_assets ||--o{ maintenance_records : maintained
    equipment ||--o{ maintenance_schedules : "scheduled for"
    equipment_assets ||--o{ condition_reports : inspected

    quotations ||--o{ quotation_items : lines
    production_packages ||--o{ package_items : contains

    vehicles ||--o{ logistics_trips : assigned
    staff ||--o{ event_staff : works
    documents }o--|| documentable : "polymorphic"
    status_changes }o--|| statusable : "polymorphic"
```

## Foundation

| Table | Key columns | Notes |
| --- | --- | --- |
| `users` **(P1)** | name, email (unique), phone, job_title, password, is_active, last_login_at, last_login_ip, soft deletes | Login accounts only (see D5). |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` **(P1)** | spatie schema + `roles.description`, `roles.is_system` | Permission names `module.action`. |
| `settings` **(P1)** | key (unique), value (json text), updated_by → users | Cached; typed by the settings schema in code. |
| `audit_logs` **(P1)** | user_id → users (set null), user_name, event, auditable_type, auditable_id, description, old_values json, new_values json, ip_address, user_agent, url, created_at | Append-only: the model refuses update/delete; no edit routes. Indexed on (auditable_type, auditable_id), user_id, event, created_at. |
| `notifications` **(P1)** | Laravel schema (uuid, type, notifiable morph, data json, read_at) | In-app channel. |
| `sequences` **(P1)** | key, period, next_value; unique(key, period) | Row-locked by `ReferenceGenerator`. |
| `lookups` **(P2)** | group, key, label, sort_order, is_active, is_system, meta json; unique(group, key) | Groups so far: `condition` (meta: blocks_allocation, sets_status), `location_type` (meta: is_storage), `unit`. Later: event types, budget ranges, maintenance types, staff roles, vehicle types. |
| `status_changes` **(P3)** | statusable morph, from_status, to_status, user_id, note, created_at | Timeline for requests, events, quotations, load lists. |
| `documents` **(P3)** | documentable morph, category (lookup key), original_name, disk, path, mime, size, checksum sha256, uploaded_by, visibility (`internal`/`customer`), soft deletes | Private disk only. |

## Inventory

| Table | Key columns | Notes |
| --- | --- | --- |
| `equipment_categories` **(P2)** | parent_id (self), name, slug (unique), description, icon, sort_order, is_active, soft deletes | Subcategories via `parent_id`. |
| `locations` **(P2)** | parent_id (self), name, code (unique), type (warehouse/site/transit/maintenance/other), address, is_active, soft deletes | Future `branch_id`. |
| `asset_statuses` **(P2)** | code (unique), label, description, group, tone, is_allocatable, is_manual, is_system, is_active, sort_order | Seeded: available, reserved, allocated, checked_out, in_transit, deployed, on_site, under_inspection, maintenance_required, under_maintenance, damaged, lost, retired, unavailable. Behaviour: ARCHITECTURE D23/D24. |
| `equipment` **(P2)** | category_id, name, sku (unique), manufacturer, model, tracking_mode (`serialized`/`bulk`), unit, description, image_path, replacement_value_kobo, low_stock_threshold, is_active, soft deletes | Catalogue item. |
| `equipment_assets` **(P2)** | equipment_id, asset_tag (unique), serial_number (unique per equipment, nullable), barcode (unique, nullable), qr_token (unique, ULID), status_id, condition (lookup key), location_id, purchase_date, purchase_cost_kobo, current_value_kobo, supplier, warranty_expires_on, last_inspected_at, next_maintenance_due_on, usage_count, notes, soft deletes | Physical unit; `qr_token` drives the future scan URL `/app/scan/{qr_token}`. |
| `stock_levels` **(P2)** | equipment_id, location_id, bucket (`available`/`quarantine`), quantity (unsigned); unique(equipment_id, location_id, bucket) | Bulk items. Quantity can never go negative (unsigned + service check). |
| `inventory_transactions` **(P2)** | type (`App\Enums\InventoryTransactionType`), equipment_id, asset_id (nullable), quantity (signed for adjustments/write-offs), from/to location, from/to status, from/to condition, from/to bucket, event_id (FK added in Phase 4), user_id + user_name, note, occurred_at | Immutable ledger: the model refuses update/delete. |

## Requests, customers, events

| Table | Key columns | Notes |
| --- | --- | --- |
| `customers` **(P3)** | name, company, email (normalised, indexed), phone (E.164, indexed), address, notes, needs_review, soft deletes | De-duplication: see ARCHITECTURE risks. |
| `services` **(P3)** | name, slug (unique), description, icon, sort_order, is_active, is_public | Public form shows `is_public && is_active`. |
| `event_requests` **(P3)** | reference (unique), public_token (unique), customer_id, event_name, event_type (lookup), event_type_other, event_date, venue, venue_meta json, phone, email, contact_person, company, duration_days, starts_at, ends_at, setup_at, has_existing_design, budget_range (lookup), requirements, additional_info, services_other, status, assigned_to → users, submitted_ip, converted_event_id | Status enum + `status_changes`. |
| `event_request_service` **(P3)** | event_request_id, service_id | Pivot. |
| `notes` **(P3)** | notable morph, body, user_id, user_name | Internal notes on any record; never public. |
| `events` **(P4)** | reference (unique), event_request_id (unique), customer_id, name, event_type, venue, venue_meta, setup_starts_at, starts_at, ends_at, breakdown_ends_at, status, project_manager_id (→ users), production_manager_id (→ staff), budget_kobo, production_requirements, soft deletes | Hold window = setup_starts_at → breakdown_ends_at; indexed. Notes, documents and status history are polymorphic. |
| `event_service` **(P4)** | event_id, service_id | Pivot. |
| `staff` **(P4)** | user_id (nullable, unique), name, phone, email, role (lookup), is_active, soft deletes | |
| `event_staff` **(P4)** | event_id, staff_id, role (lookup), notes; unique(event_id, staff_id) | Double-booking is checked against the event's hold window (D39). |

## Availability, allocation, load-out, returns

| Table | Key columns | Notes |
| --- | --- | --- |
| `equipment_requirements` **(P5)** | event_id, equipment_id, quantity, notes; unique(event_id, equipment_id) | What the event needs (per catalogue item). |
| `equipment_allocations` **(P5)** | event_id, equipment_id, asset_id (null for bulk), location_id (bulk source), quantity, hold_starts_at, hold_ends_at, state (`reserved`/`checked_out`/`returned`/`released`), allocated_by, checked_out_at, returned_at, released_at, return_outcome | Overlap indexes on (equipment_id, state, hold_starts_at, hold_ends_at) and (asset_id, state, hold_starts_at, hold_ends_at). Never deleted; release reasons go to the audit log. |
| `load_lists` **(P5)** | event_id (unique), reference (unique), status (pending/picked/loaded/checked/dispatched), notes, prepared_by, dispatched_by, dispatched_at | Status history in `status_changes`. |
| `load_list_items` **(P5)** | load_list_id, allocation_id (unique), case_label, status, checked_by, checked_at, note | |
| `return_checks` **(P5)** | event_id, user_id + user_name, notes, returned_count, missing_count, damaged_count | One per check-in session (partial returns allowed). |
| `return_check_items` **(P5)** | return_check_id, allocation_id, outcome (returned/missing/damaged/needs_inspection/needs_maintenance), quantity, location_id, note | Missing/damaged outcomes also write ledger rows and notify inventory managers. |

## Maintenance & condition

| Table | Key columns |
| --- | --- |
| `maintenance_records` | asset_id (or equipment_id for bulk), type (lookup), issue, description, reported_at, reported_by, technician_id → staff, starts_at, completed_at, cost_kobo, parts_used, next_due_on, status |
| `maintenance_schedules` | equipment_id or asset_id, type, interval_days, next_due_on, last_done_on, is_active |
| `condition_reports` | asset_id, from_condition, to_condition, note, reported_by, source (return/inspection/manual) — photos via `documents` |

## Logistics, commercial

| Table | Key columns |
| --- | --- |
| `vehicles` | name, registration (unique), type (lookup), capacity, default_driver_id → staff, status, location_id, soft deletes |
| `logistics_trips` | event_id, vehicle_id, driver_id, direction (outbound/return), pickup_location, delivery_location, scheduled_at, loading_status, dispatch_status, delivery_status, notes |
| `logistics_trip_crew` | trip_id, staff_id |
| `quotations` | reference, customer_id, event_id / event_request_id, status, issued_on, valid_until, subtotal_kobo, discount_kobo, tax_kobo, total_kobo, notes, terms, prepared_by, sent_at, viewed_at, accepted_at |
| `quotation_items` | quotation_id, section (services/equipment/labour/transport/logistics/other), description, equipment_id / service_id (nullable), quantity, days, unit_price_kobo, line_total_kobo, sort_order |
| `production_packages`, `package_items` | name, description, is_active / package_id, item type, references, quantity, unit_price_kobo |

## Integrity rules enforced in the database

- Unique: user email, asset tag, barcode, qr_token, (equipment, serial number), sku, references, (equipment, location, bucket) stock bucket, (key, period) sequences.
- Unsigned quantities on stock and allocations (no negative inventory).
- Foreign keys `restrict` on anything with history (assets, events, customers) so history cannot be orphaned; archiving uses soft deletes.
- Overlap (double allocation) is enforced in the allocation transaction (D9) because MySQL lacks exclusion constraints.
