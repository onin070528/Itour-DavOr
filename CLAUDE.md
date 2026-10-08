# iTOUR Development Rules

These rules are the permanent, project-wide source of truth for all work on iTOUR. Task documents (for example `docs/objective3_directory_geospatial_prompt.md`) add task-specific scope and phases on top of these rules; they never override them. If a task appears to conflict with this file, report the conflict and ask before proceeding.

---

## 1. Project Identity

Project Name:
iTOUR: An Integrated Tourism Information and Monitoring System with Tourist Experience Analytics for Davao Oriental

Project Type:
Web-based Tourism Information and Monitoring System

Purpose:
The system supports tourism information management, tourist arrival monitoring, tourism reporting, tourism analytics, geospatial tourism services, and AI-assisted tourism information for Davao Oriental.

The system has four primary user groups:

1. Public User / Tourist
2. Tourism Establishment / Tourism Entity
3. LGU Tourism Administrator
4. Provincial Tourism Office (PTO) Administrator

---

## 2. System Architecture

The system follows a client-server architecture:

```text
Browser / Client (PWA)
    -> Nginx (web server)
    -> Laravel application (PHP)
    -> PostgreSQL on Google Cloud SQL
```

### Client

The system is accessed through modern web browsers and supports Progressive Web App functionality where applicable.

Public users, tourism establishments, LGU Tourism Administrators, and PTO Administrators access the system through the client.

### Application

Laravel is the primary web application framework.

PHP is used for server-side application logic.

Nginx serves as the web server.

### Database

PostgreSQL is the primary relational database.

Google Cloud SQL is the managed PostgreSQL database service used for deployment.

PostgreSQL remains the source of truth for application data.

### Geospatial

Latitude and longitude are stored as separate source-of-truth fields.

Geographic distance calculations use the Haversine formula in PostgreSQL (see section 10).

PostGIS is not used. Do not introduce PostGIS or any other GIS extension unless explicitly approved.

### External Services and Integrations

Mapbox:
- map visualization
- map markers
- coordinate selection
- location picker

OpenAI API:
- AI-powered tourism assistance (the tourism inquiry chatbot) using GPT-4.1 mini

Simple QrCode:
- establishment-specific QR code generation

PWA (Vite / vite-plugin-pwa):
- service worker
- caching
- installable application behavior

Also in use:
- RESTful endpoints served by the Laravel application
- SEO-related page metadata

### Out of Scope

The itinerary generator is permanently out of scope. Do not add it to code, documentation, architecture, requirements, or future plans.

---

## 3. User Roles

The stored role values are defined in `App\Enums\UserRole` (`pto_administrator`, `lgu`, `establishment`). Public users have no role and no account.

The reporting and management hierarchy is:

```text
Tourism Establishments / Tourist Attractions / Other Tourism Entities
    -> LGU Tourism Administrator
    -> PTO Administrator
```

LGU is an application authorization and management level. It is not a separate server layer or database layer.

### Public User / Tourist

Public users do not create system accounts.

Public users may:

- browse tourism information
- search tourism destinations and services
- use public geospatial search
- view public destination and establishment information
- submit tourist arrival information through establishment-specific QR codes
- submit ratings, reviews, and feedback
- use the tourism inquiry chatbot

Public users must never access management functions.

### Tourism Establishment / Tourism Entity

Authorized tourism establishments and tourism entities may:

- manage their authorized tourism information
- access assigned QR codes
- manage tourist arrival records
- submit required tourism records
- access authorized establishment functions

### LGU Tourism Administrator

LGU Tourism Administrators represent the municipal tourism level.

They may:

- manage tourism information within their assigned municipality
- manage LGU-authorized records
- review tourism records submitted by establishments and tourism entities
- return establishment submissions for correction
- submit and resubmit records to the PTO for review
- consolidate municipal tourism records
- generate municipal tourism reports
- submit consolidated municipal reports to the PTO
- manage LGU-authorized destination information

LGU Tourism Administrators may not publish, archive, or restore destination records. Those actions belong to the PTO.

### PTO Administrator

PTO Administrators have province-wide access.

They may:

- manage province-wide tourism information
- review LGU submissions
- publish approved records
- return records for correction
- archive records
- restore archived records (restoration returns a record to Draft, never directly to Published)
- monitor province-wide tourism statistics
- generate provincial tourism reports
- manage other province-wide administrative functions

---

## 4. LGU Municipality Authorization

An LGU user is permanently bound to exactly one municipality through the authenticated user's `mun_id` (`tbl_users.mun_id`, foreign key to `tbl_municipalities.mun_id`). Records carry the same relationship (for example `tbl_listings.mun_id`). `mun_id` is the existing column name; do not rename it to `municipality_id` and do not add redundant municipality columns.

Every LGU query, policy, controller action, report, export, dashboard aggregate, AJAX endpoint, and direct-record access must derive municipality ownership server-side from the authenticated user and the related record.

Never trust the following request-supplied values for authorization:

- municipality IDs
- establishment IDs
- destination IDs
- role or owner IDs
- route parameters
- hidden form fields
- query parameters
- client-side filters

A cross-municipality access or modification attempt must return HTTP 403 and be recorded through the existing denied-access logger, `SecurityLogger::accessDenied()` (table `tbl_security_logs`), without logging passwords or other sensitive request data.

LGU users may access and manage only records belonging to their assigned municipality and authorized managing level.

PTO users retain province-wide access across all municipalities.

Client-side hiding, disabled controls, or filtering is never sufficient for authorization.

Authorization must be enforced through server-side authentication, policies, middleware, controllers, services, and related mechanisms.

Reuse the existing municipality-scoping implementation (`App\Policies\ListingPolicy`, `Listing::scopeVisibleTo()`, `AuthorizesOwnMunicipality`, the `role:` and `lgu.municipality` middleware, and the municipality-reassignment guard in `Listing::booted()`).

Do not duplicate municipality authorization logic.

---

## 5. Security and Privacy

All authorization decisions must be performed server-side.

All request data must be validated server-side.

Use Laravel's validated input mechanisms (`$request->validated()` or Form Requests).

Do not use `$request->all()` where validated input is required.

Sensitive fields must not be freely mass assignable. Status, workflow, ownership, municipality, and audit fields are set by application logic, never directly from request input.

The application must not expose:

- passwords
- authentication secrets
- API keys
- internal credentials
- unpublished records
- internal administrative fields
- owner information where it is not public (for example `lst_owner_name`)
- QR UUID/token values (`lst_uuid`) on public directory pages
- internal identifiers, owner linkage IDs, and authorization or workflow metadata
- tourist GPS coordinates

All untrusted text (names, descriptions, contacts, hours, remarks) is rendered with Blade escaping. Never render it as raw HTML.

### Tourist Location Privacy

The user's current location is temporary information used only for nearby search.

Tourist coordinates must never be stored or retained in:

- database
- session
- cache
- analytics
- application logs

Do not put temporary tourist coordinates into GET URLs.

Find Near Me must use POST with a JSON body. The server rounds the submitted coordinates to about 4 decimal places, uses them only for the current request, and discards them afterward.

Never send the tourist's current location to Mapbox Directions or any other external directions service. Directions links carry only the destination's coordinates and are built only by `App\Support\DirectionsLink`.

If geolocation fails or is denied, the directory must continue working normally.

### Image Security

Validate uploads for:

- MIME type
- extension
- file size
- dimensions

Use safe random file names.

Do not allow executable uploads.

Reuse the existing image storage implementation and ownership checks (`App\Services\EstablishmentImageUploader`, `config/establishment_images.php`, the gated `establishmentImages.file` route, and `App\Policies\ImagePolicy`).

### Rate Limiting

Apply reasonable rate limits to public search and geolocation endpoints, defined with `RateLimiter::for()` in `App\Providers\AppServiceProvider` like the existing limiters.

Rate limits must not unnecessarily prevent normal tourism-directory use (tourists often share one Wi-Fi IP address).

### Logging

Do not change the existing logging architecture. The project has two logging mechanisms, each with its own purpose:

- `App\Support\SecurityLogger::accessDenied()` (table `tbl_security_logs`) for authorization and security denials, such as a cross-municipality 403 attempt or a direct LGU request for a PTO-only action.
- `App\Support\OperationLogger` (table `tbl_operation_logs`) for normal workflow and operation events: created, updated, submitted, returned, published, unpublished, suspended, archived, restored.

Do not record authorization denials through `OperationLogger`, and do not record workflow events through `SecurityLogger`.

Both tables are append-only (`database/GRANTS.md`). The older `audit_logs` table was dropped; do not write to it.

Do not create a new logging system or logging table.

Never log:

- passwords
- API keys
- authentication secrets
- unnecessary sensitive request data
- tourist GPS coordinates

---

## 6. Database Rules

PostgreSQL is the primary database.

Preserve the current database architecture.

Before creating or modifying tables, columns, relationships, or migrations:

1. inspect the existing schema
2. identify current relationships
3. identify current naming
4. identify existing functionality
5. determine whether the requirement can be implemented by extending the existing schema

Use additive and reversible migrations.

Every migration must provide a working rollback (`down()`).

Never use:

`migrate:fresh`

Never reset, wipe, or restore the database as part of feature development.

**Single approved exception — disposable test databases only.** Laravel's `RefreshDatabase` (which runs `migrate:fresh`) may run only against:

- the dedicated, disposable PostgreSQL testing database `itour_testing`, for automated tests
- the existing SQLite test database configured in `phpunit.xml` (`storage/testing.sqlite`)

Never run `migrate:fresh`, `RefreshDatabase`, or any reset against the development database, staging, production, or any shared or persistent application database.

Never delete existing data as part of normal feature development.

Never rewrite unrelated records.

Never rename existing tables or columns without explicit approval.

Do not modify unrelated database structures.

Do not backfill or transform existing data without first showing the plan and receiving approval.

Existing schema names take precedence over idealized names (for example `mun_id`, `lst_slug`, `lst_lat`, `lst_lng`).

---

## 7. Existing Files First

Before creating any:

- file
- model
- migration
- controller
- route
- policy
- middleware
- service
- component
- Blade view
- configuration
- database structure

search for an existing implementation.

Reuse existing functionality whenever possible.

Extend instead of rebuild.

Do not create duplicate:

- modules
- tables
- services
- configurations
- routes
- authorization logic
- components

Do not overwrite unrelated changes.

Keep Blade templates separate from CSS and JavaScript: styles belong in `resources/css`, scripts in `resources/js`, bundled through Vite.

---

## 8. Existing System Features

The following are existing system components and must be preserved unless a task explicitly requires modification:

- authentication
- login
- Turnstile
- role-based access control
- LGU municipality authorization
- establishment workflow
- QR generation
- tourist arrival registration
- municipal reporting
- monthly reporting
- tourism monitoring
- chatbot
- PWA/service worker
- public tourism directory
- tourist feedback
- sentiment analysis
- translation
- existing categories
- tour guides
- local delicacies
- pasalubong centers
- emergency hotlines
- existing audit/security logging
- existing design system

Do not modify unrelated functionality merely to simplify implementation.

---

## 9. Tourism Directory Records

### One table for destinations and establishments

Destinations and tourism establishments share the existing `tbl_listings` table (`App\Models\Listing`). A destination-only record (a tourist attraction such as a beach or waterfall) has `lst_category = 'destinations'` and the "Tourist Destinations" category in `tbl_categories`. Every other category is an establishment.

Do not create a separate destinations table, a separate destination model, or a separate "LGU Destinations" module. LGU destination screens live under the existing Tourism Directory > Establishments page (Attractions view).

Categories come from the existing `tbl_categories` lookup. Do not duplicate category definitions.

### Lifecycle

There is one lifecycle for every destination listing. Existing stored status values are kept; they are mapped to the logical states and never renamed or migrated:

| Logical state | Destination-only record | Establishment listing |
|---|---|---|
| Draft | `DRAFT` | `DRAFT` (`FOR_LGU_REVIEW` while the establishment's submission waits for its LGU) |
| Pending Review | `FOR_PTO_REVIEW` | `FOR_PTO_REVIEW` |
| For Correction | `FOR_CORRECTION` (remarks in `lst_review_remarks`) | `FOR_CORRECTION` |
| Published | `Active` | `PUBLISHED` |
| Archived | `Archived` | `Archived` |

The flow is Draft -> Pending Review -> (For Correction -> Pending Review) -> Published -> Archived.

The existing `UNPUBLISHED` state (the PTO pulled a published establishment listing back for revision) is preserved and is never public.

The existing `Suspended` state is preserved as currently defined: stored value `Suspended` on `lst_status`, set only by the PTO through the Tourism Directory "Change Status" action (`Pto\DirectoryController::updateStatus()`) with a required reason. A suspended record is never public; for an establishment it also turns off QR eligibility (`Listing::INACTIVE_ESTABLISHMENT_STATUSES`). Do not redefine it. A suspended destination is never reinstated directly to Published; reinstatement follows Suspended -> Draft -> Pending Review -> Published. This is unrelated to the account status `usr_status = 'Suspended'` on `tbl_users`.

Authority:

- LGU submits and resubmits records of its own municipality for PTO review.
- Only the PTO publishes, returns for correction (remarks required), suspends, archives, restores, and reinstates.
- The LGU may not archive. A direct LGU archive attempt returns 403 and is logged through `SecurityLogger::accessDenied()`.
- Restoring an archived record returns it to Draft; reinstating a suspended destination returns it to Draft. Either way it must pass review again before it is public.
- New directory records the PTO creates through the Tourism Directory create action (`Pto\DirectoryController::store()`) start as Draft instead of being published automatically. A PTO-managed destination is published only through the review workflow — the PTO's Submit for Review, then Approve & Publish — never directly from Draft. A PTO-created establishment starts as Draft and follows the existing LGU "Request to feature" workflow. Every step is audit-logged; there is no hidden or automatic publishing. Unrelated existing establishment behavior is preserved.
- The workflow is implemented in `App\Services\ListingPublishWorkflow` (with `App\Services\AttractionRecordService` for destination-only records). Extend these; do not create a parallel workflow.

### Public visibility

`Listing::isPubliclyVisible()` is the single source of truth for public visibility (destination: `Active`; establishment: `PUBLISHED`). Do not add a "show in public directory" flag or a redundant "active" flag. Draft, pending, returned, suspended, unpublished, and archived records are never public. A listing appears in nearby search only when it is publicly visible and has valid coordinates.

### Edits to published records

Edits to public content of a published listing are held in the existing `lst_pending_changes` column (fields listed in `Listing::PUBLIC_CONTENT_FIELDS`). The published version stays public until the PTO approves the changes; a PTO return keeps the published version and sends the held changes back with remarks. Do not replace this with another versioning system. Visitor information and entrance fee changes require PTO review through this mechanism.

### Managing level and office

`mun_id` is the physical location of a destination and is required. The managing level (LGU or PTO) decides who may edit a destination: the LGU may edit only LGU-managed destinations in its own municipality; the PTO may edit all.

For destination records, the managing office reuses the existing `lst_contact_office` field where appropriate. Do not globally redefine this field: for establishments it keeps its existing meaning ("Contact person / office"). Do not create a `lst_managing_office` column unless actual implementation proves it is necessary, and then only with approval.

### Slugs

Public URLs use `lst_slug` (unique). Slugs are generated once and stay stable when a record is renamed.

---

## 10. Geospatial Rules

The application uses PostgreSQL with the Haversine formula for geographic distance. PostGIS and other GIS extensions are not used, and no `geometry` or `geography` column is created.

`lst_lat` and `lst_lng` (decimal degrees) remain the source-of-truth coordinate fields. Do not create duplicate editable coordinate data.

All authoritative geographic distance calculations execute server-side in PostgreSQL, in one reusable service (`NearbySearchService`). Controllers orchestrate requests; they do not calculate distance. Do not duplicate distance calculations in controllers, models, Blade templates, JavaScript, database triggers, or other services. The browser never calculates authoritative distances.

The Haversine calculation, with R = 6371 km (mean Earth radius):

```text
a = sin²(Δlat / 2) + cos(lat1) × cos(lat2) × sin²(Δlng / 2)
a = LEAST(1, GREATEST(0, a))      -- clamp against floating-point drift
c = 2 × asin(sqrt(a))
distance = R × c
```

Angles are converted to radians before use. A latitude/longitude bounding-box prefilter may narrow the candidate rows for performance, but the Haversine distance is the final radius test and the sort key.

Results are sorted nearest first, limited, and paginated. Distances are shown as straight-line distance ("450 m away" under 1 km, "1.2 km away" otherwise), not road distance.

Coordinates are validated server-side: numeric, latitude -90 to 90, longitude -180 to 180, both present together, and inside the configurable Davao Oriental coordinate guard. Records with missing or invalid coordinates are excluded from nearby results; this is never an error.

Directory settings (destination types, coordinate guard, radius options, default and maximum radius, page sizes, GPS rounding, the Find Near Me rate limit) live in one configuration file, `config/tourism_directory.php`. Do not hard-code these values elsewhere or create overlapping configuration files.

Mapbox and GeoJSON use longitude-first order (`[lng, lat]`). Keep the order explicit in code and comments.

### Mapbox

Mapbox is responsible for visualization and coordinate selection only. It is not the source of truth for tourism records and does not calculate authoritative distances.

Reuse the existing Mapbox configuration: `config('services.mapbox.token')`, read from `env('MAPBOX_SECRET_KEY')`. Do not create a second Mapbox configuration.

The token rendered to the browser must be a public Mapbox token (`pk.*`). Every browser-facing token goes through `App\Support\MapboxToken::browserToken()`, the single gate that withholds any non-`pk.` value. Never expose a secret token (`sk.*`) to the browser in HTML or JavaScript. Never print or log token values. Development may use an unrestricted public token; production uses a separate public token restricted by URL in the Mapbox account, verified during deployment.

A failed map load must never break the directory.

---

## 11. Documentation Integrity

Implementation must remain consistent with the capstone manuscript.

When implementation changes:

- architecture
- technology
- user roles
- database structure
- system workflow
- major functionality

identify the corresponding manuscript sections that may require updating.

Do not silently change the documented system architecture through code.

Do not claim implementation that has not actually been completed.

Technical documentation for the project lives in `docs/`.

---

## 12. ITD Coding Standards and Guidelines

The ITD Coding Standards and Guidelines (reference copy: `photos/coding standards.png`) are the authoritative coding standards for the project.

Do not replace them with personal coding preferences.

### Precedence with the existing Laravel code

Follow the ITD standards wherever they are compatible with the existing Laravel project. Where they conflict:

- Existing Laravel / PSR-12 formatting enforced by Laravel Pint wins over the ITD vertical-brace rule. Keep the project's existing brace style (class and method braces on their own line, control-structure braces on the same line).
- Do not reformat existing files to satisfy ITD formatting.
- Existing schema, class, method, route, and view names take precedence over idealized ITD names. Do not rename existing working names without a concrete task reason and approval.
- Framework-required file names (for example PascalCase PHP class files) follow the framework.

### Database Name

The database name must use an abbreviated form of the project name and be prefixed with `db`.

### Table Names

- Use descriptive plural or collective nouns.
- Prefix table names with `tbl_` (the project convention, for example `tbl_listings`).
- Separate multiple words with `_`.
- Table names longer than 30 characters must be justified.

### Column Names

- Column names are lowercase.
- Separate multiple words with `_`.
- Use the project's established 2–3 letter table prefix convention (for example `lst_` for `tbl_listings`).
- Use the established `<prefix>_id` convention for identifiers, reused as the column name in related tables (for example `mun_id`).
- Clearly identify the data represented by the column.

### File Names

- Keep file names short, simple, and descriptive.
- Use alphanumeric characters.
- Separate words with `_`.
- File names are lowercase except where a framework or platform requires otherwise.
- PHP files use `.php`.

### Function, Method, and Class Names

- Names must be descriptive of their role.
- Class names begin with uppercase.
- Methods and functions use mixed case and begin with lowercase.
- Avoid unnecessary abbreviations and acronyms.
- Do not use all-uppercase acronyms in identifiers (`Pto`, `Lgu`, `Qr`, `Gps`).
- Boolean-returning functions should use a positive-question form such as `isEnabled`.
- New private and protected methods may use the ITD underscore prefix (for example `_transition()`). Do not rename existing methods only to satisfy this convention.

### Variable Names

Variable names must follow the ITD naming convention.

Use the following data-type prefixes where applicable:

- `byt` = Byte
- `int` = Integer
- `lng` = Long
- `flt` = Float
- `dbl` = Double
- `cur` = Currency
- `chr` = Character
- `str` = String
- `txt` = Text
- `dt` = Date
- `tm` = Time
- `dtm` = Date/Time
- `bln` = Boolean
- `arr` = Array
- `obj` = Object
- `err` = Error

Constants use uppercase naming.

Avoid unnecessary abbreviations.

Array elements use single quotes.

### Code Layout

- Use four spaces for indentation.
- Brace placement follows the existing Pint-enforced project style (see Precedence above).
- Use one variable declaration, constant definition, or statement per line.
- Use one space around binary operations and assignments.
- Do not use embedded assignments unnecessarily.
- SQL keywords must be uppercase.
- Align major SQL keywords.
- Avoid the `goto` statement.
- Loop variables should be initialized immediately before the loop.

### Control Structures

- Include a `default` case in switch statements where possible.
- Avoid unnecessarily complex control structures.

### Conditionals

Complex conditional expressions must be simplified.

Use temporary boolean variables when conditions become difficult to read or maintain.

### Comments

- Comments must be written in English.
- Function descriptions must appear directly above the function.
- Long or complex code blocks must have summary comments.
- Long or complex blocks may include closing-brace comments where useful.

### Program Headers

Programs must include a header containing:

- system name
- purpose
- programmer name(s)
- copyright notice

Do not invent programmer names. Use the dominant existing project header:

```text
iTOUR — Davao Oriental Tourism Information System

Purpose: <what this file does>
Programmer/s: iTOUR Development Team
Copyright (c) 2026 iTOUR Development Team. All rights reserved.
```

Do not introduce another header style.

### Error Handling

Use appropriate error handling procedures to capture and report errors, with friendly user-facing messages.

External service failures, map failures, and geolocation failures must not cause the public tourism directory to fail.

---

## 13. Testing and Quality

Test new functionality before declaring it complete.

Run focused tests before the complete test suite:

```bash
php artisan test --filter=<RelevantTest>
php artisan test
```

The main test suite runs on SQLite (`phpunit.xml`). Do not move the whole suite to PostgreSQL unless explicitly approved.

Geospatial tests run against a dedicated PostgreSQL testing database named `itour_testing`, because SQLite has no trigonometric functions. `itour_testing` is disposable: tests may rebuild it with `RefreshDatabase` / `migrate:fresh` (the only exception to the rule in section 6). Never run automated tests against the development or production database. PostGIS is not installed in the testing database.

Before reporting completion, run:

```bash
git diff --check
./vendor/bin/pint --test
```

Pint is run in report-only mode (`--test`). Do not auto-fix with Pint unless explicitly instructed, and never reformat unrelated files.

Regression: existing tests, QR check-in, arrival recording, monthly and municipal reporting, establishment workflow, and login must keep passing. Report pre-existing failures separately from failures caused by the current change.

---

## 14. Version Control and Working Practice

Do not stage or commit changes unless explicitly instructed.

Never reset, discard, restore, or overwrite unrelated user work in the working tree.

Keep diffs small and focused on the task. Do not perform unrelated cleanup or reformatting.

Work one phase at a time when a task is phased: summarize, explain how to test, then stop and wait for approval before the next phase.

Do not read or print secret values from `.env` or other credential sources.

Do not add new packages or dependencies without approval.
