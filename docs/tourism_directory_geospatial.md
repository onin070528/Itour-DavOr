# iTOUR Tourism Directory and Nearby Search (Objective 3)

Technical explanation, defense demo script, and manuscript reconciliation for Objective 3:
*a tourism information directory with geospatial nearest neighbor search that serves as a one-stop shop for accessing tourist destinations and nearby tourism-related services within Davao Oriental.*

Validation status (2026-10-08): implementation complete; automated tests pass (SQLite and PostgreSQL); **real-phone validation not yet performed — ready for manual device validation** (section 6).

---

## 1. How it works

### Data

- PostgreSQL is the source of truth. Destinations and tourism establishments share one table, `tbl_listings` (model `App\Models\Listing`). A destination-only record has the category "Tourist Destinations"; every other category is an establishment.
- Every listing that takes part in nearby search stores its location as two decimal-degree columns, `lst_lat` and `lst_lng`. There is no PostGIS and no spatial (`geometry`/`geography`) column.
- Destination records also carry a destination type (Beach, Waterfall, Mountain, Cave, Museum, Heritage Site, Nature Park, Other), visitor information, an entrance fee, a managing level (LGU or PTO), and who created and last updated them.
- A listing is public only when it is published (`Listing::isPubliclyVisible()`: a destination is `Active`, an establishment is `PUBLISHED`). Draft, pending review, returned, suspended, unpublished, and archived records never appear on public pages or in nearby results.

### Distance calculation

- All distance is calculated on the server, in PostgreSQL, by one service: `App\Services\NearbySearchService`. No other code — and no browser JavaScript — calculates distance.
- Method: the Haversine formula on a sphere of radius 6371 km:

  ```text
  a = sin²(Δlat / 2) + cos(lat1) × cos(lat2) × sin²(Δlng / 2)
  a = LEAST(1, GREATEST(0, a))      (clamped to [0, 1] against floating-point drift)
  c = 2 × asin(sqrt(a))
  distance = 6371 × c               (kilometres)
  ```

- Steps for one search: take the reference point (a destination's stored location, or a visitor's temporary location) → keep only published listings with valid coordinates → narrow them with a latitude/longitude bounding box (uses the `(lst_lat, lst_lng)` index) → calculate the Haversine distance → keep those within the radius → sort nearest first → return one page.
- The bounding box only narrows the candidates; the Haversine distance is the final radius test and the sort key.
- Radius options: 1, 5, 10, 25, or 50 km; default 10 km; maximum 50 km (enforced on the server). Pages of 20 results; at most 5 per category on a destination page. All values come from `config/tourism_directory.php`.
- Distances are straight-line ("450 m away", "1.2 km away"), not road distance.

### Maps

- Mapbox only displays: the directory's Map view, the destination location map, and the management location picker. It never calculates distance and is never the source of truth.
- The browser only ever receives a public Mapbox token (`pk.`); `App\Support\MapboxToken::browserToken()` withholds any other token.

### Find Near Me (visitor location)

- The visitor chooses Allow Location; the browser's native permission prompt follows. The location is requested once (no continuous tracking).
- It is sent once to iTOUR by `POST /find-near-me` with a JSON body — never in a URL — validated (including a coarse Davao Oriental boundary: latitude 6.20–8.10, longitude 125.80–126.70), rounded on the server to 4 decimal places (about 11 m), used for that one search, and discarded.
- It is not stored in the database, session, cache, logs, cookies, or browser storage, not sent to analytics, and not included in the response. The endpoint is rate-limited (60 requests per minute per visitor IP, configurable).
- If location is denied or unavailable, the directory keeps working: keyword search, filters, and each destination's Find Nearby list remain available.

### Directions

- "Get Directions" is an external Google Maps link that contains only the destination's coordinates (`App\Support\DirectionsLink`). iTOUR never sends the visitor's location to Google Maps, Mapbox, or any routing service.

### Management workflow

- One lifecycle for destination records: Draft → Pending Review → (For Correction → Pending Review) → Published → Archived. Suspended and Archived records return to Draft, never directly to Published.
- An LGU manages destinations only in its own municipality, and only LGU-managed ones; the PTO manages all, and is the only office that publishes, returns for correction, suspends, archives, or restores.
- Edits to a published destination's public content (including visitor information and entrance fee) are held for PTO review while the published version stays public.
- Denied access is recorded by `SecurityLogger` (`tbl_security_logs`); workflow events by `OperationLogger` (`tbl_operation_logs`).

---

## 2. Offline (PWA) scope

The Progressive Web App caches only the public Emergency Hotlines page (network first, with the cached copy as a fallback) and a small storage-notice script. Everything in Objective 3 needs a connection: the directory, search, destination pages, live maps, Find Near Me, and nearby results. The chatbot, live reports, and other server features also need a connection. iTOUR does not work fully offline.

---

## 3. Evidence

| Check | Result |
|---|---|
| Full SQLite test suite | All pass; PostgreSQL-only tests are skipped on SQLite (it has no trigonometric functions) |
| PostgreSQL geospatial tests (`itour_testing`) | All pass |
| Haversine accuracy | Fixture points 0.99 km apart: PostgreSQL result 0.9934 km, within 1e-9 km of an independent calculation |
| Privacy | A search only reads from the database; no log entry, session value, or response field contains the visitor's coordinates |
| Security | Every Objective 3 management route refuses another municipality's records (403, security-logged); no public page exposes internal ids, UUIDs, owner names, or workflow status |
| Query plan (controlled test) | On 20,000 synthetic rows in the test database, the nearby query used the `(lst_lat, lst_lng)` index to narrow to 382 candidates and returned 194 results; execution took about 1.9 ms on the development machine. This is a controlled test observation, not a guaranteed production response time |

The exact test totals of the final run are recorded in `docs/objective3_directory_geospatial_prompt.md` (section 14).

---

## 4. Three-minute defense demo script

1. **(0:00) Directory.** Open Explore. Point out: one directory for destinations and establishments; only published listings appear. Search "Sleeping Dinosaur". Then clear it and filter Municipality = City of Mati and Destination type = Beach (Dahican Beach). Mention: search and filters run on the server, 20 per page.
2. **(0:40) Map view.** Switch to Map. Point out: destination (teal) and tourism service (orange) pins, the list under the map, "Show on map". Mention: Mapbox only displays; the data comes from PostgreSQL.
3. **(1:05) Destination page.** Open Dahican Beach. Show visitor information, entrance fee, managing office, the location map, and Nearby Tourism Services grouped by category with distances. Mention: the distances are Haversine, calculated in PostgreSQL by one service, nearest first, at most 5 per category.
4. **(1:40) Find Nearby.** Click Find Nearby; change the radius from 10 km to 25 km to show more places, then filter one category. To show the empty state, pick 1 km with a category that has nothing that close; point out the "search within" a wider radius option.
5. **(2:05) Find Near Me.** Open the Nearby page (`/nearby`, topbar "Find Nearby"): read the privacy notice, Use my location (or search a place), show the nearest places and their pins. Mention: the location is sent once by POST, rounded to about 11 m, and never stored. (Requires HTTPS — use the cloudflared address.)
6. **(2:35) Directions and workflow.** Click Get Directions: Google Maps opens with the destination only. Close with the workflow: an LGU submits, only the PTO publishes; a suspended destination goes back to Draft and must be reviewed again.

---

## 5. Manuscript reconciliation

The manuscript is not stored in this repository, so it has not been edited here. The team should apply these corrections so the document matches the implemented system:

| Manuscript location | Current statement | Corrected statement |
|---|---|---|
| Section 1.1 | "The system uses PostgreSQL, PostGIS, and Mapbox to support Geospatial Nearest Neighbor Search" | "The system uses PostgreSQL to store tourism records and their coordinates, calculates geographic distance in PostgreSQL with the Haversine formula for the nearest neighbor search, and uses Mapbox to display maps." |
| Table 3 (software requirements) | PostGIS 3.6.4 | Remove the PostGIS row. Geospatial distance: PostgreSQL (Haversine formula in SQL, no extension). Keep PostgreSQL and Mapbox GL JS (v3.7.0). |
| Software requirements text | Mentions PostGIS | Replace with the Haversine-in-PostgreSQL description above. |
| Architecture figure and its description | PostGIS shown as a component | Remove PostGIS; show Browser → web server (Nginx) → Laravel → PostgreSQL (Google Cloud SQL), with Mapbox as an external visualization service. |
| Feature comparison table | PostGIS-based nearest neighbor search | "Haversine-based nearest neighbor search in PostgreSQL". |
| Use case diagram | — | Include: browse/search directory, view destination, Find Nearby, Find Near Me, Get Directions (public); create/edit destination and submit for review (LGU); review, publish, return, suspend, archive, restore (PTO). |
| Data dictionary (`tbl_listings`) | — | Add `lst_visitor_information` (text), `lst_entrance_fee` (varchar), `lst_managing_level` (varchar: `lgu`/`pto`), `lst_created_by` and `lst_updated_by` (bigint, FK to `tbl_users.usr_id`); note that `lst_type` holds the destination type for destination records and `lst_contact_office` is shown as the managing office for destinations. |
| PWA / offline description | — | Use the wording of section 2: only the Emergency Hotlines page and a small script are cached; maps, Find Near Me, the directory, the chatbot, and reports need a connection. |
| Sections 2.4 (Development) and 2.5 (Testing results) | — | Describe the phased implementation and the automated test results; report the 20,000-row query plan only as a controlled test observation; report real-phone testing only after it has actually been performed. |

Also confirm in the manuscript:

- There is no itinerary generator.
- The chatbot: the current codebase's chatbot is a browser-side placeholder; there is no OpenAI (GPT-4.1 mini) integration in the repository yet. Describe the chatbot as implemented only once that integration exists.

---

## 6. Real-phone validation

Status: **ready for manual device validation** — not yet performed. Browsers allow geolocation only on HTTPS (or `localhost`), so a phone test needs an HTTPS tunnel. The steps are in `docs/objective3_directory_geospatial_prompt.md` (Phase 6, "Phone test over HTTPS"). Check on the phone: the permission prompt, Allow Location, Not Now and denial, a successful search, retry, radius changes, no-results handling, outside-province handling where possible, destination-only directions, map behavior, and graceful failure. Record the outcome here once performed.
