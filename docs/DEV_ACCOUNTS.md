# Development Demo Accounts

**NOT FOR PRODUCTION.** These accounts exist only in local/staging environments — the seeder that creates them (`Database\Seeders\RbacDemoAccountSeeder`) refuses to run when `APP_ENV=production`.

| Role | Email | Scope |
|---|---|---|
| PTO | `tourism@itourdavor.gov.ph` | Province-wide |
| LGU | `tourism.mati@itourdavor.gov.ph` | Municipality: Mati |
| ESTABLISHMENT | `establishments@itourdavor.gov.ph` | Municipality: Mati |

**Password (all three):** `itour-davor@2026`

## Where these come from

- Password is read from `SEED_DEMO_PASSWORD` in `.env` (see `.env.example` for the placeholder) — never hard-coded in application code.
- Seeded by `database/seeders/RbacDemoAccountSeeder.php`, called from `database/seeders/DatabaseSeeder.php`.
- The seeder also creates two minimal demo establishments (`iTOUR Demo Establishment (Mati)` and `iTOUR Demo Establishment (Baganga)`) used as RBAC test fixtures — the Establishment demo account is linked to the Mati one.
- Running the seeder is idempotent (`updateOrCreate`, keyed by email/slug) — running `php artisan db:seed` repeatedly does not create duplicates.

## Running it

```bash
php artisan migrate:fresh --seed
```

or, to seed only this piece against an already-migrated database:

```bash
php artisan db:seed --class=Database\\Seeders\\RbacDemoAccountSeeder
```

## Other seeded accounts

These 3 are additive — they exist alongside the broader illustrative demo dataset `database/seeders/UserSeeder.php` already seeds (province-wide PTO/LGU/Establishment sample accounts used by the User Management pages), which all use the password `password`. Do not remove either dataset without checking what currently depends on it (`MunicipalReportSeeder`, various feature tests).

## RBAC foundation

These accounts exercise the three fixed roles (`pto_administrator` / `lgu` / `establishment` on `App\Enums\UserRole`) and municipality/establishment scoping added in the Phase 0 RBAC foundation — see `app/Policies/`, `app/Models/Municipality.php`, and the `municipality_id`/`establishment_id` columns on `users`.
