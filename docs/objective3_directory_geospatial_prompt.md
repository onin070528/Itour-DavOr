# Objective 3 Task: Tourism Information Directory with Haversine-Based Geospatial Nearest-Neighbor Search (iTOUR)

**Status (2026-10-08):** Phases 0–5 completed and approved. Phase 6 implemented and awaiting approval. Do not begin Phase 7 until it is approved.

This document adds Objective 3 scope, decisions, and phases on top of `CLAUDE.md`. Every rule in `CLAUDE.md` applies (architecture, roles, LGU municipality authorization, security and privacy, database safety, existing files first, tourism directory records, geospatial rules, ITD Coding Standards, testing, version control). This document does not repeat those rules except where Objective 3 needs task-specific detail. If this document and `CLAUDE.md` ever disagree, stop and ask.

**Project objective being implemented (manuscript 1.3.2.3):** *Develop a tourism information directory with geospatial nearest neighbor search that serves as a one-stop shop for accessing tourist destinations and nearby tourism-related services within Davao Oriental.*

---

## 1. How to Work

1. Work one phase at a time. At the end of each phase: summarize, show how to test it, then stop and wait for approval. Never batch phases.
2. Before creating any file, table, column, route, configuration, or component, search for an existing one and extend it in place.
3. If something is unclear or conflicts with `CLAUDE.md` or the manuscript, ask instead of assuming.
4. Additive, reversible migrations only. Never `migrate:fresh`. No backfill or data transformation without showing the plan and receiving approval.
5. Do not read or print `.env` values. Never hard-code or commit keys.
6. Do not stage or commit.
7. Keep it simple, secure, maintainable, and easy to explain at a defense. No machine learning, no GIS extension, no new packages without approval.

---

## 2. Scope

### In scope

- one unified public tourism directory (destinations and establishments)
- destination data fields and destination types
- one destination lifecycle with LGU/PTO management
- coordinate validation and the Davao Oriental coordinate guard
- Mapbox visualization and the location picker
- server-side nearest-neighbor (nearby) search with radius filters
- destination detail page with grouped nearby services
- Find Near Me (browser geolocation, temporary use only)
- public keyword search and filters
- map markers and empty states
- security, performance, tests, and documentation

### Out of scope

- tourist feedback analytics, sentiment analysis, translation, issue detection (Objective 4); an existing "Share Your Experience" button stays as it is
- the itinerary generator (permanently out of scope)
- PostGIS or any other GIS extension
- tour guides, delicacies, pasalubong centers, and emergency hotlines directory items (left untouched)
- QR generation/routing, the arrival form, monthly/municipal reporting, accounts/RBAC core, login and Turnstile, the chatbot, PWA/service worker, the design system

---

## 3. Phase 0 Findings (completed, no implementation changes)

Phase 0 investigated the codebase and the local database read-only. No code, schema, data, route, migration, or configuration was changed.

| Area | Finding |
|---|---|
| Listing table | Destinations and establishments share `tbl_listings` (`App\Models\Listing`). A destination-only record has `lst_category = 'destinations'` and the "Tourist Destinations" category. There is no separate destination table. |
| Existing destination fields | `lst_slug` (unique), `lst_name`, `lst_description`, `mun_id` (nullable FK, not indexed), `lst_barangay`, `lst_lat`/`lst_lng` (double, nullable), `lst_contact_office`, `lst_contact_phone`, `lst_email`, `lst_website`, `lst_hours`, `lst_status` (not indexed), `lst_type` (NULL on all destinations), `cat_id`, `lst_review_remarks`, `lst_pending_changes`. |
| Missing destination fields | visitor information, entrance fee, managing level, created by / updated by. |
| Internal fields | `lst_uuid` (QR token), `lst_owner_name`, `lst_reporting_mode`, `lst_is_qr_enabled` must never appear on public pages. |
| Statuses | Destination: `DRAFT`, `FOR_PTO_REVIEW`, `FOR_CORRECTION`, `Active`, `Suspended`, `Archived`. Establishment: `DRAFT`, `FOR_LGU_REVIEW`, `FOR_PTO_REVIEW`, `FOR_CORRECTION`, `PUBLISHED`, `UNPUBLISHED`, `Suspended`, `Archived`. |
| Workflow | `App\Services\ListingPublishWorkflow` (submit, publish, return with remarks, unpublish, held changes) and `App\Services\AttractionRecordService` (destination-only create/edit). |
| Pending changes | Edits to public content of a published listing are held in `lst_pending_changes` while the published version stays public (`Listing::PUBLIC_CONTENT_FIELDS`). |
| Public visibility | `Listing::isPubliclyVisible()`: destination `Active`, establishment `PUBLISHED`. |
| Suspended status | Stored value `Suspended` on `lst_status`. Set only by the PTO through "Change Status" (`Pto\DirectoryController::updateStatus()`, reason required, logged through `OperationLogger`). Allowed values there: destinations `Active`/`Suspended`/`Archived` (so Suspended -> Active is possible directly today); establishments `Suspended`/`Archived` only (no way back). A suspended record is never public; for an establishment it also turns off QR eligibility (`Listing::INACTIVE_ESTABLISHMENT_STATUSES`). No listing is currently Suspended. Unrelated to the account status `usr_status = 'Suspended'`. |
| Workflow gaps vs. final decisions | The LGU can archive destinations (`Lgu\DirectoryController::archiveDestination`, route `lgu.directory.destinations.archive`). The PTO "Change Status" action can set an archived or suspended destination straight back to `Active`. PTO-created records start live (`Active`/`PUBLISHED`). |
| Logging | Workflow actions: `OperationLogger` -> `tbl_operation_logs`. Authorization/security denials (cross-municipality 403): `SecurityLogger::accessDenied()` -> `tbl_security_logs` (6 existing call sites). Both append-only. The old `audit_logs` table was dropped; `App\Models\AuditLog` is unused. |
| Mapbox | Mapbox GL JS v3.7.0 from the Mapbox CDN. Token: `config('services.mapbox.token')` <- `env('MAPBOX_SECRET_KEY')`, rendered into 4 views (`explore`, `nearby`, `components/listing-details-modal`, `pto/directory/index`). No token is configured locally; no token is hard-coded in source or build output. The production value's type (`pk.` vs `sk.`) could not be verified from the repository. |
| Browser distance code | `resources/js/app.js`: `haversineDistanceKm()` and `initNearbyMap()` (landing "Open Nearby map") compute the nearest place in the browser. |
| External directions | The landing listing-details modal "Get directions" sends the visitor's location to the Mapbox Directions API. |
| Public directory | `/explore` (`ExploreController`) and the landing page load every public listing through `App\Support\TourismCatalog::listings()` and filter client-side; no server-side search or pagination. Detail page: `/listings/{slug}` (`ListingDetailController`). |
| Coordinate entry | Typed number inputs; validation is `nullable|numeric|between` (`SaveAttractionRequest`, `SaveEstablishmentRequest::listingFieldRules()`, reused by the PTO form); no "both together" rule and no Davao Oriental guard. |
| PostGIS | Not installed and not available on the local PostgreSQL 18.4. Not needed: the final decision is Haversine. |
| Tests | Pest with `RefreshDatabase` on SQLite (`phpunit.xml`, `storage/testing.sqlite`). SQLite has no trigonometric functions, so geospatial tests need PostgreSQL. No PostgreSQL testing database exists yet. `./vendor/bin/pint --test` passed. |
| Rate limits | `login`, `report-export`, `qr-checkin`, `establishment-image-upload` in `AppServiceProvider`. None for public search or nearby endpoints yet. |
| Images | `EstablishmentImageUploader`: JPEG/PNG/WebP, max 5 MB, min 1200x800, 5 live photos per listing, random 40-character names on the private `local` disk, served through the gated `establishmentImages.file` route. Legacy `lst_image` files live in `storage/app/public/itour-images`. |
| Slug bug | `Listing::uniqueSlug()` queries `where('slug', ...)`; the column is `lst_slug`. Introduced when merge `8a1070f` brought in the ITD column rename but did not update this line (written on commit `2c4830f`). On PostgreSQL it throws `column "slug" does not exist`, breaking LGU Add Establishment and LGU Add Tourist Attraction. Tests pass only because SQLite treats the unknown `"slug"` as a string literal. |
| `lst_contact_office` | Destinations: labeled "Contact office" (LGU form, public detail page, Explore table, landing modal). Establishments: labeled "Contact person / office" on LGU screens. |
| Destination types | `lst_type` is NULL on all 8 destinations. `config/establishment_categories.php` deliberately excludes "Tourist Destinations" from establishment types. |
| Phone testing | No HTTPS tunnel tool is installed; `APP_URL` is `http`. |

---

## 4. Final Decisions (locked)

| # | Decision |
|---|---|
| D1 | Geospatial method: PostgreSQL + server-side Haversine in one `NearbySearchService`. No PostGIS, no GIS extension, no `geometry`/`geography` column. |
| D2 | Extend the existing LGU Establishments page (Attractions view) and the PTO Tourism Directory screens. No new LGU Destinations module. |
| D3 | One lifecycle (Draft -> Pending Review -> For Correction -> Pending Review -> Published -> Archived), mapped onto the existing stored statuses without renaming or migrating data (mapping table in `CLAUDE.md` section 9). Only the PTO publishes, returns, suspends, archives, restores, and reinstates. The LGU cannot archive. Restore returns Archived -> Draft. A suspended destination is never reinstated directly to Published: Suspended -> Draft -> Pending Review -> Published. New directory records created through the PTO create action (`Pto\DirectoryController::store()`) start as Draft and are published through an explicit, audit-logged PTO action; unrelated existing establishment behavior is preserved. `Suspended` keeps its existing definition; `Suspended`, `UNPUBLISHED`, and `FOR_LGU_REVIEW` are kept and are never public. |
| D4 | Public visibility uses the existing published state (`Listing::isPubliclyVisible()`). No "show in public directory" flag. Nearby search additionally requires valid coordinates. |
| D5 | No separate or redundant "active" flag. |
| D6 | Coordinates are picked with a Mapbox draggable marker that fills the latitude/longitude fields; values are always re-validated server-side. Davao Oriental coordinate guard: latitude 6.20–8.10, longitude 125.80–126.70 (coarse, configurable). |
| D7 | Radius options 1, 5, 10, 25, 50 km; default 10 km; maximum 50 km; 20 results per page; 5 results per group on the destination detail page. All configurable. |
| D8 | Find Near Me: POST with a JSON body, rate-limited, coordinates rounded server-side to about 4 decimals, used for the current request only, never stored or logged (database, session, cache, analytics, logs). |
| D9 | Reuse the existing `lst_pending_changes` workflow. Published content stays public while edits wait for PTO approval. Visitor information and entrance fee changes both require PTO review. |
| D10 | `mun_id` stays required (physical location). Managing level: LGU or PTO; the LGU edits only LGU-managed destinations in its own municipality, the PTO edits all. For destination records, the managing office reuses `lst_contact_office` where appropriate. The establishment meaning of `lst_contact_office` ("Contact person / office") is not redefined. No `lst_managing_office` column unless actual implementation proves it necessary (and then only with approval). |
| D11 | Destination types, exactly: Beach, Waterfall, Mountain, Cave, Museum, Heritage Site, Nature Park, Other. Stored in the existing `lst_type` column. The list lives in `config/tourism_directory.php`. No Island or Viewpoint type. |
| D12 | Geospatial tests run against a dedicated PostgreSQL testing database `itour_testing` (no PostGIS). The main suite stays on SQLite unless explicitly approved. |
| D13 | Phone geolocation testing uses an HTTPS tunnel, preferably `cloudflared`. `http://192.168.x.x:8000` does not provide a secure context for geolocation. |
| D14 | Pint runs in report-only mode (`./vendor/bin/pint --test`). Existing Laravel/Pint formatting wins over the ITD vertical-brace rule. |
| D15 | Tour guides, delicacies, pasalubong centers, and hotlines stay untouched. |
| Mapbox | Reuse `config/services.php` -> `config('services.mapbox.token')` / `MAPBOX_SECRET_KEY`. The browser-facing token must be a public `pk.*` token; never expose `sk.*` to the browser. Never print or log token values. Development may use an unrestricted public token; production uses a separate URL-restricted public token, verified during deployment (does not block any phase). |
| Directions | External directions links (Google Maps) carry only the destination's coordinates. The tourist's location is never sent to Mapbox Directions or any other directions service. |
| Landing map | The landing "Open Nearby map" will use the same Find Near Me implementation; the browser `haversineDistanceKm()` is removed. No second nearby-search implementation. |
| Logging | The existing logging architecture is unchanged. Authorization/security denials, including cross-municipality 403 attempts and direct LGU requests for PTO-only actions: `SecurityLogger::accessDenied()` (`tbl_security_logs`). Normal workflow/operation events: `OperationLogger` (`tbl_operation_logs`). No new logging system. |
| Headers | New and modified files use the dominant existing header with "Programmer/s: iTOUR Development Team" (`CLAUDE.md` section 12). |

---

## 5. Requirements

### R1. One unified public directory

A single public Tourism Directory (no login) containing:

- **Tourist Destinations / Attractions** (destination-only records): beaches, waterfalls, mountains, caves, museums, heritage sites, nature parks, other attractions. A destination is not an establishment and is never forced into an establishment category.
- **Tourism Establishments / Services**: the existing establishment categories in `tbl_categories` (Accommodation, Food & Dining, Farm & Agri-Tourism, Wellness & Spa, Travel & Tours, Tourist Transport, Recreation & Activities, MICE & Events, Others).

Public flow: open the directory -> choose Destinations or Establishments -> search/filter -> open details -> view the map location -> Find Nearby -> nearest records shown. The existing `/explore` page (category menu, search, municipality filter, Grid/Table/Map views) is extended with server-driven data, not rebuilt.

### R2. Destination data (extend `tbl_listings`)

Reuse existing columns: `lst_slug`, `lst_name`, `lst_description`, `mun_id`, `lst_barangay` (address/location description), `lst_lat`, `lst_lng`, `lst_contact_office` (managing office for destinations), `lst_contact_phone`, `lst_email`, `lst_website`, `lst_hours`, `lst_status`, `lst_type` (destination type), and the existing cover-photo handling for the featured image.

Add only what is missing, following the `lst_` naming convention: visitor information, entrance fee, managing level (LGU/PTO), created by, updated by. Archive instead of deleting; never hard-delete destination records.

### R3. Lifecycle

Per D3 and `CLAUDE.md` section 9. One lifecycle for destination-only records and establishment listings.

### R4. Management roles

- **LGU Tourism Administrator:** creates and edits LGU-managed destinations in its own municipality, uploads images, enters descriptions, visitor information, entrance fee, contact information and hours, sets coordinates with the map picker, submits and resubmits for PTO review, views its municipality's records. Cannot publish, archive, or restore.
- **PTO Administrator:** province-wide. Views all records, manages PTO-managed destinations, reviews submissions, publishes, returns for correction with required remarks, archives, restores (to Draft).
- **Public:** sees only publicly visible records.
- Authorization is server-side, reusing the existing policies and municipality scoping. An LGU for one municipality cannot view or modify another municipality's records (403 plus a `tbl_security_logs` entry).

### R5. Coordinates and validation

Every record taking part in nearby search has `lst_lat` and `lst_lng` in decimal degrees. Server-side validation: numeric; latitude -90..90; longitude -180..180; both present together; inside the Davao Oriental coordinate guard (D6). Records with missing or invalid coordinates are excluded from nearby results without error. Coordinates are entered with the Mapbox map picker (click or drag a marker), shown read-only as text.

### R6. Mapbox

Visualization and coordinate selection only. PostgreSQL is the source of truth for destinations, establishments, municipalities, categories, coordinates, and information. Reuse the existing Mapbox setup; public `pk.*` token only in the browser.

### R7. Nearest-neighbor search

Reference point (a destination's stored coordinates, or the tourist's temporary location) -> compute Haversine distance in PostgreSQL to qualifying records -> exclude invalid coordinates -> apply the radius -> sort ascending by distance -> return results with distance, limited and paginated. Implemented only in `NearbySearchService` (section 6).

Distance display: "450 m away" under 1 km, "1.2 km away" otherwise, labeled as straight-line distance, not road distance.

### R8. Coverage and filters

Nearby search covers destinations and establishments. Filters: All, Destinations, and the existing establishment categories (no duplicate category definitions). Radius options per D7. Simple filter UI in the existing iTOUR style.

### R9. Destination detail page

Name, municipality, image, description, visitor information, entrance fee, hours, location, Mapbox map, and Nearby Tourism Services grouped by category (for example Accommodation, Food & Dining, Other Nearby Destinations), each with distance, nearest first, at most 5 per group with a "see all" link, plus "View Details". Actions: View on Map, Find Nearby, and Get Directions (an external Google Maps link with only the destination coordinates). No internal IDs, `lst_uuid`, owner data, or workflow metadata. Do not redesign the page beyond this.

### R10. Find Near Me

Button "Find Near Me". Flow: short explanation and privacy notice -> [Allow Location] / [Not Now] -> the browser's native permission prompt -> coordinates sent by POST JSON for one temporary search -> results nearest first plus map markers.

Wording: never "tracking". Notice: *"iTOUR uses your current location only to identify nearby tourism destinations and services. Your exact location is not permanently stored."*

If location is denied or unavailable: the directory keeps working; show "Location access was not allowed." and offer normal search, the municipality and category filters, and destination-based Find Nearby. A visitor outside Davao Oriental is told so and offered the normal directory.

### R11. Public search

Search by destination name, establishment name, municipality, and category; filters for municipality, category, and destination type; paginated. Keyword search is separate from the nearest-neighbor calculation. Server-driven; no client-side authoritative filtering and no unbounded result sets.

### R12. Map markers and empty states

Distinct markers for the reference destination, nearby destinations, and nearby establishments. Empty state: "No tourism services were found within the selected radius." with options to increase the radius, browse the full directory, or change the category. A destination without coordinates shows a "location not set" message on its map section, and Find Nearby is disabled with that reason.

### R13. Security

Per `CLAUDE.md` sections 4 and 5: validated input only; explicit fillable fields; status, workflow, ownership, municipality, and audit fields set by application logic (an LGU's `mun_id` always comes from the authenticated user); Blade escaping; existing image upload validation and storage; rate limits on public search and nearby endpoints; no exposure of non-public records, owner data, or `lst_uuid`; every lifecycle transition logged through `OperationLogger`.

### R14. Performance

Server-side distance filtering and ordering with a result limit and pagination. Add only needed indexes (for example `mun_id`, `lst_status`, and latitude/longitude for the bounding-box prefilter). No GIS infrastructure.

### R15. Documentation (for the defense)

A short technical explanation in `docs/` (planned name `docs/tourism_directory_geospatial.md`): data stored in PostgreSQL; destinations and establishments carry latitude/longitude; Mapbox only displays; the reference point is the selected destination or the tourist's temporary location; PostgreSQL calculates Haversine distance; results are filtered by radius and category and sorted nearest first; tourist GPS is never stored. Include a 3-minute demo script.

---

## 6. Haversine Specification

All authoritative distance calculation lives in `NearbySearchService`. Nothing else calculates distance.

Formula (R = 6371 km, angles in radians):

```text
Δlat = radians(lat2 - lat1)
Δlng = radians(lng2 - lng1)

a = sin²(Δlat / 2) + cos(radians(lat1)) × cos(radians(lat2)) × sin²(Δlng / 2)
a = LEAST(1, GREATEST(0, a))          -- clamp to [0, 1] against floating-point drift
c = 2 × asin(sqrt(a))
distance_km = 6371 × c
```

Implemented query shape (`NearbySearchService::findNearbyListings()`; the reference point is bound once as a one-row subquery, never concatenated into the SQL):

```sql
SELECT  *
FROM    (
        SELECT  tbl_listings.*,
                6371 * 2 * ASIN(SQRT(LEAST(1, GREATEST(0,
                    POWER(SIN(RADIANS(tbl_listings.lst_lat - reference.ref_latitude) / 2), 2)
                    + COS(RADIANS(reference.ref_latitude)) * COS(RADIANS(tbl_listings.lst_lat))
                    * POWER(SIN(RADIANS(tbl_listings.lst_lng - reference.ref_longitude) / 2), 2)
                )))) AS distance_km
        FROM    tbl_listings
        CROSS JOIN (SELECT CAST(? AS DOUBLE PRECISION) AS ref_latitude,
                           CAST(? AS DOUBLE PRECISION) AS ref_longitude) AS reference
        WHERE   <publicly visible>                                  -- Listing::scopePubliclyVisible()
        AND     lst_lat / lst_lng NOT NULL and within valid ranges
        AND     lst_lat BETWEEN ? AND ?                              -- bounding-box prefilter only
        AND     lst_lng BETWEEN ? AND ?
        ) AS tbl_listings
WHERE   distance_km <= ?
ORDER BY distance_km, lst_name, lst_id
```

Rules:

- The bounding box (the exact spherical box around the radius) only narrows candidates; the Haversine distance is the final radius test and the sort key.
- Destinations and establishments share `tbl_listings`, so one query covers both; no UNION is needed.
- Optional filters: category ids (the "Tourist Destinations" category = destinations only), and exclusion of the reference listing.
- Pagination (`paginate()`, 20 per page) and grouping (`findNearbyGroupedByCategory()`, at most 5 per category with `ROW_NUMBER()`) wrap the same query.
- Public output (`toPublicResult()`): type, slug, name, category, subtype, municipality, barangay, latitude, longitude, distance in meters, distance label, image URL, detail URL. No internal fields.
- Mapbox/GeoJSON use `[lng, lat]` order; keep the order explicit in code and comments.

Test fixture example: two points at latitude 6.9550, longitudes 126.2170 and 126.2260, are about 0.99 km apart (Haversine on a 6371 km sphere); assert within about ±1%.

---

## 7. Configuration

One configuration file, `config/tourism_directory.php` (created in Phase 1), holds:

- `destination_types` (D11)
- `coordinate_bounds`: the Davao Oriental coordinate guard (D6)
- `results_per_page` (20): every public result list — the directory and the nearby lists (D7)
- `nearby.radius_options_km`, `nearby.default_radius_km`, `nearby.max_radius_km` (D7)
- `nearby.results_per_group` (5) (D7)
- `nearby.coordinate_precision` (4): GPS rounding (D8)
- `nearby.find_near_me_per_minute` (60): the Find Near Me rate limit per visitor IP

No other file duplicates these values.

---

## 8. Phases

### PHASE 0: Audit and decision locking. Status: COMPLETED

Investigated: `tbl_listings` and the shared destination/establishment representation, statuses and the review workflow, `lst_pending_changes`, operation and security logging, Mapbox configuration and token handling, the browser Haversine code and external directions call, the absence of PostGIS, the SQLite test setup, rate limits, image handling, `lst_contact_office` usage, destination types, and schema/naming issues (including the slug bug). Findings are in section 3; decisions are locked in section 4. No implementation changes were made.

### PHASE 1: Foundation and small related fixes. Status: COMPLETED

- `config/tourism_directory.php` (section 7).
- Additive, reversible migration(s) on `tbl_listings` for the missing fields (R2): visitor information, entrance fee, managing level, created by, updated by; indexes on `mun_id`, `lst_status`, and latitude/longitude. Inspect naming first and use the smallest compatible addition.
- Model updates: fillable/casts for the new content fields; managing level and audit columns not mass assignable.
- Coordinate validation rule (both-or-neither, ranges, Davao Oriental guard) reused by the attraction, establishment, and PTO forms.
- `NearbySearchService` foundation (Haversine query per section 6).
- Destination type mapping for the existing 8 destinations — approved before Phase 2 and applied by migration `2026_10_08_110000_backfill_destination_type_and_managing_level_on_listings_table` (empty values only; reversible):

  | Destination | Applied type | Managing level |
  |---|---|---|
  | Dahican Beach | Beach | NULL (LGU) |
  | Aliwagwag Falls Eco-Park | Waterfall | NULL (LGU) |
  | Mount Hamiguitan Range Wildlife Sanctuary | Mountain | NULL (LGU) |
  | Pujada Bay | Other | NULL (LGU) |
  | Subangan Museum | Museum | `pto` |
  | Cape of San Agustin | Other | NULL (LGU) |
  | Pusan Point | Other | NULL (LGU) |
  | Sleeping Dinosaur Island | Other | NULL (LGU) |

- Known small related fixes (section 9).
- Deliverable: schema and foundation ready; migrate and rollback verified (never `migrate:fresh`). STOP.

### PHASE 2: Lifecycle and management workflow. Status: COMPLETED

- Extend `ListingPublishWorkflow`, `AttractionRecordService`, `ListingPolicy`, and the existing LGU Establishments/Attractions and PTO Tourism Directory screens. No parallel workflow.
- Draft, Pending Review, For Correction, Published, Archived per D3.
- PTO-only publish, return (remarks required), archive, and restore (Archived -> Draft).
- Suspended destinations: the PTO "Change Status" action no longer sets a destination from `Suspended` (or `Archived`) straight to `Active`; reinstatement returns it to Draft (Suspended -> Draft -> Pending Review -> Published). The existing meaning of `Suspended` is kept, and the establishment Suspend/Archive behavior is unchanged.
- New records created through `Pto\DirectoryController::store()` (destinations and establishments) start as Draft; explicit, audit-logged PTO publish action for them. No other establishment behavior changes.
- Remove the LGU archive buttons (`lgu/directory/attractions/show.blade.php`, `lgu/directory/destinations.blade.php`). A direct LGU archive request returns 403 and is logged through `SecurityLogger::accessDenied()`, not `OperationLogger`.
- Pending changes: add visitor information and entrance fee to `Listing::PUBLIC_CONTENT_FIELDS` and to the PTO review field labels (`Pto\DestinationReviewsController`), so both require PTO review.
- Managing level enforcement (D10) and municipality authorization through the existing policies.
- Map picker fields (coordinates) on the forms; full map behavior lands in Phase 5.
- Every transition logged through `OperationLogger`.
- Deliverable: records can be created, submitted, returned, published, archived, and restored with correct authorization. STOP.

### PHASE 3: Geospatial backend. Status: COMPLETED

- Complete `NearbySearchService`: PostgreSQL Haversine, `a` clamped to [0, 1], bounding-box prefilter, configurable Davao Oriental guard, radius handling (max 50 km), category/destination filters, exclusion of the reference record, public-visibility and valid-coordinate rules, limit and pagination, distance formatting helper.
- `itour_testing` PostgreSQL database and a geospatial test group (D12). Run with `DB_CONNECTION=pgsql DB_DATABASE=itour_testing php artisan test --group=geospatial` (Git Bash). `tests/TestCase.php` refuses to refresh any PostgreSQL database other than `itour_testing`. Tests first, with fixed fixture coordinates: nearest-first ordering, inside vs. outside radius, category filter, destinations-only filter, missing and invalid coordinates, no results, non-public records excluded, reference record excluded, pagination, the 0.99 km fixture.
- Deliverable: a tested, reusable search service. STOP.

### PHASE 4: Directory, search, filters, and details. Status: COMPLETED

- Extend `/explore` into the server-driven unified directory: Destinations/Establishments browse, keyword search, municipality/category/destination-type filters, pagination, empty states. Replace the full client-side payload with paginated server data.
- Public detail pages for destinations (R9) and establishments (public-safe fields only), by slug; 404 for non-public records.
- Destination detail page: grouped Nearby Tourism Services (5 per group) from `NearbySearchService`.
- SEO basics (title, meta description, canonical) reusing existing layout metadata.
- Deliverable: a tourist can browse, search, filter, and open details without logging in. STOP.

### PHASE 5: Mapbox integration and map UI. Status: COMPLETED

- Detail-page map with the reference marker and nearby markers (three distinct styles); list and map stay in sync; list/map toggle on mobile.
- LGU/PTO location picker: draggable marker feeding the coordinate fields, with the Davao Oriental guard message; server-side validation remains authoritative.
- Reuse the existing Mapbox setup and token configuration; graceful message if the map fails to load; no whole-directory payload for distance calculation.
- Deliverable: maps and the location picker work on desktop and phone-size screens. STOP.

### PHASE 6: Find Near Me. Status: COMPLETED (real-phone validation pending)

- Rate-limited POST JSON endpoint using `NearbySearchService`; coordinates rounded to about 4 decimals; never persisted, logged, cached, put in the session, or sent to analytics.
- Explanation and privacy notice, Allow/Not Now, native permission, nearest-first results and map markers, radius and category filters, denied/unavailable/timeout fallbacks, outside-Davao-Oriental message.
- Replace the landing "Open Nearby map" browser calculation (`haversineDistanceKm()` in `resources/js/app.js`) with this implementation.
- Replace the listing-details modal Mapbox Directions call with an external Google Maps link carrying only the destination coordinates.
- Phone testing over HTTPS through `cloudflared`.
- Deliverable: Find Near Me works with permission and fails gracefully without it. STOP.

Phone test over HTTPS (browsers allow geolocation only on HTTPS or localhost):

1. Install once: `winget install --id Cloudflare.cloudflared`.
2. `npm run build`, then `php artisan serve --host=127.0.0.1 --port=8000`.
3. In another terminal: `cloudflared tunnel --url http://localhost:8000` and copy the printed `https://….trycloudflare.com` address.
4. Temporarily set `APP_URL` in `.env` to that address and run `php artisan config:clear`. In the `local` environment only, an `https://` `APP_URL` makes every link and asset URL use it (`AppServiceProvider`).
5. On the phone, open the address and check: the permission prompt, results with distance labels, Not Now, denied permission, retry, radius changes, and that Get Directions opens Google Maps with the destination only.
6. Afterwards restore `APP_URL` and run `php artisan config:clear` again.

### PHASE 7: Security, performance, and regression testing. Status: COMPLETED

- Authorization tests: PTO province-wide; LGU limited to its own municipality and managing level (cross-municipality edit/submit/archive returns 403 and is security-logged); public cannot reach management; an LGU cannot set `mun_id`, status, managing level, or created/updated by.
- Lifecycle tests: unpublished states never public; published content stays public while changes are pending; visitor information and entrance fee changes require PTO review; restore (Archived) and reinstatement (Suspended) both return to Draft; denials are written to `tbl_security_logs`, workflow events to `tbl_operation_logs`.
- Geospatial tests through the real endpoints; Find Near Me privacy (no coordinates in database tables or log files); rate limits; XSS escaping; upload validation; no owner data or `lst_uuid` exposure; pagination and empty states; query performance check.
- Regression: existing suite, QR check-in, arrival recording, reporting, login.
- `git diff --check` and `./vendor/bin/pint --test` (report only). Pre-existing failures reported separately.
- Deliverable: a security checklist with results and a passing test report. STOP.

### PHASE 8: Final verification and documentation. Status: COMPLETED (real-phone validation pending)

- Final Objective 3 regression and architecture verification.
- `docs/tourism_directory_geospatial.md` and the 3-minute defense demo script (R15).
- Implementation summary.
- Confirm: no itinerary generator added; no PostGIS or GIS extension introduced; tourist location privacy rules hold; LGU/PTO authorization boundaries hold.
- Propose any `CLAUDE.md` additions and the manuscript updates needed (section 11) for approval; do not apply them automatically.
- Deliverable: final report. STOP.

---

## 9. Known Phase 1 Small Fixes (all three done in Phase 1)

1. **Slug query (confirmed).** `Listing::uniqueSlug()` (`app/Models/Listing.php`) queries `slug`; correct it to `lst_slug`. Affected: `AttractionRecordService::create()`, `ManagesDestinationListings::uniqueDestinationSlug()` (used by `Lgu\EstablishmentsController::store()`), and the legacy `Lgu\DirectoryController::storeDestination()`. The fix changes no stored slugs; no existing slug has a numeric suffix.
2. **PTO duplicate slug helper (confirmed duplicate).** `Pto\DirectoryController::uniqueListingSlug()` re-implements `Listing::uniqueSlug()` (correctly, with `lst_slug`; fallback base `listing` instead of `destination`). Consolidate onto `Listing::uniqueSlug()` only if the behavior stays identical for existing callers.
3. **Audit entity label (confirmed, related).** `Pto\DirectoryController::store()` and `::updateStatus()` log the entity as `'establishment'` even for destination records. Phase 2 changes both actions (Draft creation, archive, restore), so correct them to use `Listing::auditEntityType()` there.

No other cleanup.

---

## 10. Acceptance Rules

1. Haversine in PostgreSQL, not PostGIS.
2. Existing `tbl_listings`, not a new destination table.
3. `mun_id` remains the municipality relationship.
4. Existing `lst_pending_changes` is reused.
5. The PTO is the publication authority.
6. The LGU cannot archive.
7. Archived records restore to Draft.
8. PTO-created destinations start as Draft.
9. No new public-directory visibility flag.
10. No redundant active flag.
11. Visitor information changes require PTO review.
12. Entrance fee changes require PTO review.
13. Nearby default radius is 10 km.
14. Nearby maximum radius is 50 km.
15. Supported radii are 1, 5, 10, 25, and 50 km.
16. Nearby pagination is 20 per page.
17. Nearby detail groups are limited to 5.
18. Find Near Me uses POST with a JSON body.
19. Tourist GPS is not persisted, logged, cached, stored in the session, or sent to external directions services.
20. Coordinates are rounded server-side to about 4 decimals.
21. The Davao Oriental coordinate guard is latitude 6.20–8.10 and longitude 125.80–126.70.
22. The Mapbox browser token is `pk.*`, never `sk.*`.
23. The existing Mapbox configuration is reused.
24. `cloudflared` is the preferred HTTPS tunnel for phone geolocation testing.
25. The dedicated geospatial testing database is `itour_testing`.
26. Pint runs with `--test`, report only.
27. Existing Laravel formatting wins over the conflicting ITD brace rule.
28. Existing working modules remain intact.
29. No destructive database operations.
30. No unrelated cleanup.
31. No staging.
32. No commits.
33. Suspended destinations are never reinstated directly to Published (Suspended -> Draft -> Pending Review -> Published).
34. Authorization denials are logged through `SecurityLogger::accessDenied()`; workflow events through `OperationLogger`.

---

## 11. Manuscript Sections Affected by the Haversine Decision

The manuscript currently names PostGIS. These places need updating to "PostgreSQL computes great-circle (Haversine) distance in SQL; Mapbox only displays" (not changed by this task; for the team to edit):

- Table 3 (software requirements, "PostGIS 3.6.4")
- the software requirements text
- the architecture figure and its description
- section 1.1 ("The system uses PostgreSQL, PostGIS, and Mapbox ...")
- the feature comparison table

Later phases also affect the use case diagram and the data dictionary (new destination fields) and sections 2.4 (Development) and 2.5 (Testing results).

---

## 12. Resolved Decisions and Remaining Items

Remaining (none blocks the implementation):

- **Real-phone validation** of Find Near Me over HTTPS: not yet performed — ready for manual device validation (Phase 6 steps).
- **Manuscript:** not stored in this repository; apply the corrections in `docs/tourism_directory_geospatial.md` section 5.
- **Chatbot:** outside Objective 3. The repository's chatbot is a browser-side placeholder with no OpenAI integration yet; documentation must not describe it as implemented until it is.
- **Landing overview map:** still embeds every public listing's pin (public fields only), as before Objective 3.

Resolved on 2026-10-08:

- **PTO-created destinations:** published only through Submit for Review, then Approve & Publish — no direct Draft -> Published action. PTO-created establishments start as Draft and use the existing LGU "Request to feature" workflow.
- **Managing level and destination type:** both optional; a NULL managing level means LGU-managed.
- **"Created by" / "updated by":** recorded on record creation and content edits, not on status changes.

- **Logging:** existing architecture kept; `SecurityLogger::accessDenied()` for denials, `OperationLogger` for workflow events.
- **Suspended destinations:** reinstated through Draft, never directly to Published; the existing definition of `Suspended` is kept.
- **`lst_contact_office`:** reused as the managing office for destination records only; the establishment meaning is unchanged; no `lst_managing_office` unless implementation proves it necessary.
- **PTO-created records:** new records from the PTO create action start as Draft; unrelated establishment behavior is preserved.
- **Mapbox:** browser token `pk.*` only, never `sk.*`, existing `MAPBOX_SECRET_KEY` configuration reused. Production token restriction is verified during deployment and does not block any phase.

---

## 13. Phase Summary Format

```text
Files created / Files modified
Database changes (and rollback check)
Routes added or changed
Destination workflow (statuses and who can do what)
Geospatial implementation (Haversine service, indexes)
Security controls implemented
Tests performed (new, focused, full suite, git diff --check, Pint report) and existing unrelated failures
Configuration required (Mapbox token, testing database, coordinate guard, HTTPS tunnel)
Assumptions (labeled "Assumption:") and decisions still needing confirmation
How to test it manually (including a cross-municipality check and a phone test over HTTPS)
```

Do not claim anything was implemented if it was not.

---

## 14. Final Implementation Record (Phase 8, 2026-10-08)

**Status:** implementation complete; automated tests pass; **real-phone validation pending (ready for manual device validation)**. Objective 3 is not fully accepted until the device test is performed and recorded in `docs/tourism_directory_geospatial.md` section 6.

### Requirements

| Requirement | Result | Evidence |
|---|---|---|
| R1 Unified directory | Implemented, verified | `/explore` (server-driven); `ExplorePageTest` |
| R2 Destination data | Implemented, verified | `tbl_listings` columns, 8 approved types, managing level; `DestinationListingReviewTest` |
| R3 Lifecycle | Implemented, verified | `ListingPublishWorkflow`, `Listing::statusChangeOptions()`; `DestinationListingReviewTest`, `Objective3SecurityTest` |
| R4 Management roles | Implemented, verified | `ListingPolicy`, `ImagePolicy`, municipality checks; `Objective3SecurityTest`, `MunicipalityScopingTest` |
| R5 Coordinates and validation | Implemented, verified | `WithinDavaoOrientalBounds`, location picker; `LguEstablishmentManagementTest`, `ListingDetailPageTest` |
| R6 Mapbox | Implemented; data and token gate verified; interactive map behavior still needs a manual browser/phone check | `MapboxToken`, public maps; `ExplorePageTest`, `ListingDetailPageTest` |
| R7 Nearest-neighbor search | Implemented, verified | `NearbySearchService`; `NearbySearchServiceTest` (PostgreSQL) |
| R8 Coverage and filters | Implemented, verified | radius 1/5/10/25/50, categories; `PublicNearbyPagesTest`, `FindNearMeSearchTest` |
| R9 Destination detail page | Implemented, verified (map shown inline on the page; Find Nearby and Get Directions as links) | `listing-detail.blade.php`; `ListingDetailPageTest`, `PublicNearbyPagesTest` |
| R10 Find Near Me | Implemented, automated tests pass; **real-phone validation pending** | `POST /find-near-me`, `find_near_me.js`; `FindNearMeTest`, `FindNearMeSearchTest` |
| R11 Public search | Implemented, verified | `Listing::scopeMatchingPublicSearch()`; `ExplorePageTest` |
| R12 Markers and empty states | Implemented, verified (markup and data); visual check pending with R6 | legend, three marker colors, empty states; `ExplorePageTest`, `PublicNearbyPagesTest` |
| R13 Security | Implemented, verified | `Objective3SecurityTest`, `FindNearMeTest` |
| R14 Performance | Implemented, verified | no N+1 (query-count tests); query plan uses the `(lst_lat, lst_lng)` index (controlled test observation only) |
| R15 Documentation | Implemented | `docs/tourism_directory_geospatial.md` (explanation, demo script, manuscript reconciliation) |

### Final test results

| Run | Tests | Passed | Failed | Skipped |
|---|---|---|---|---|
| Full suite, SQLite (`php artisan test`) | 640 | 617 | 0 | 23 |
| Geospatial group, PostgreSQL `itour_testing` | 23 | 23 | 0 | 0 |
| Focused Objective 3 files, SQLite | 108 | 108 | 0 | 0 |

The 23 SQLite skips are exactly the PostgreSQL-only geospatial tests (SQLite has no trigonometric functions); they all pass on `itour_testing`.

Also: `npm run build` succeeds (output gitignored); `./vendor/bin/pint --test` passes on every changed PHP file; `git diff --check` is clean except a pre-existing blank line at the end of `resources/views/pto/monthly-reports/index.blade.php`, which is a user change outside Objective 3 and was left untouched.

### Known limitations

- Real-phone validation not yet performed (see section 12).
- The manuscript is not in this repository; corrections are listed in `docs/tourism_directory_geospatial.md` section 5.
- The chatbot is a browser-side placeholder (outside Objective 3).
- Only the Emergency Hotlines page and a small script are cached offline; Objective 3 features need a connection.
