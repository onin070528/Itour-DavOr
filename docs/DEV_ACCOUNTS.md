# Development Demo Accounts

**NOT FOR PRODUCTION.** These accounts exist only in local/staging environments — the seeder that creates them (`Database\Seeders\RbacDemoAccountSeeder`) refuses to run when `APP_ENV=production`.

| Role | Email | Scope | Must change password on first login |
|---|---|---|---|
| PTO (primary) | `tourism@itourdavor.gov.ph` | Province-wide | No |
| PTO (secondary) | `tourism.admin2@itourdavor.gov.ph` | Province-wide | Yes |
| LGU | `tourism.mati@itourdavor.gov.ph` | City of Mati | Yes |
| LGU | `tourism.baganga@itourdavor.gov.ph` | Baganga | Yes |
| LGU | `tourism.banaybanay@itourdavor.gov.ph` | Banaybanay | Yes |
| LGU | `tourism.boston@itourdavor.gov.ph` | Boston | Yes |
| LGU | `tourism.caraga@itourdavor.gov.ph` | Caraga | Yes |
| LGU | `tourism.cateel@itourdavor.gov.ph` | Cateel | Yes |
| LGU | `tourism.govgen@itourdavor.gov.ph` | Governor Generoso | Yes |
| LGU | `tourism.lupon@itourdavor.gov.ph` | Lupon | Yes |
| LGU | `tourism.manay@itourdavor.gov.ph` | Manay | Yes |
| LGU | `tourism.sanisidro@itourdavor.gov.ph` | San Isidro | Yes |
| LGU | `tourism.tarragona@itourdavor.gov.ph` | Tarragona | Yes |
| ESTABLISHMENT | `establishments@itourdavor.gov.ph` | Municipality: Mati | No |

**Temporary password (all of the above):** the `SEED_DEMO_PASSWORD` value in your `.env`. Only one active LGU account is allowed per municipality; the older demo LGU accounts (and `ebautista@davaooriental.gov.ph`) from `UserSeeder` are seeded **Inactive**.

Re-seeding is safe: accounts are matched by email, municipalities are only looked up (never created), and the temporary password and must-change flag are only re-applied to an account that has never set its own password.

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

These accounts are additive — they exist alongside the broader illustrative demo dataset `database/seeders/UserSeeder.php` already seeds (province-wide PTO/LGU/Establishment sample accounts used by the User Management pages), which all use the password `password`. Do not remove either dataset without checking what currently depends on it (`MunicipalReportSeeder`, various feature tests).

## RBAC foundation

These accounts exercise the three fixed roles (`pto_administrator` / `lgu` / `establishment` on `App\Enums\UserRole`) and municipality/establishment scoping added in the Phase 0 RBAC foundation — see `app/Policies/`, `app/Models/Municipality.php`, and the `municipality_id`/`establishment_id` columns on `users`.
