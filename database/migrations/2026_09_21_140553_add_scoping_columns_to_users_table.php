<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RBAC scoping columns. `organization_subtitle` (municipality) and
     * `organization_name` (establishment) stay as-is for display — these
     * two new FKs become the authorization-facing source of truth instead
     * of string-matching. Backfilled for existing rows by
     * Database\Seeders\RbacScopeBackfillSeeder.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('municipality_id')->nullable()->after('role')
                ->constrained('municipalities')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->unique()->after('municipality_id')
                ->constrained('listings')->nullOnDelete();
        });

        // Cross-driver note: SQLite (the test driver) cannot ALTER TABLE ...
        // ADD CONSTRAINT on an existing table at all, so this CHECK is
        // Postgres-only.
        //
        // Deliberately narrow: only "a PTO account can never carry a
        // municipality_id/establishment_id" is enforced at the DB level —
        // the safe, unambiguous half of the rule, and the direction most
        // worth a hard guarantee (a PTO account accidentally scoped is the
        // more dangerous misconfiguration). The full rule from the RBAC
        // spec (LGU must have municipality_id; Establishment must have
        // both) is NOT a DB CHECK, because this app's pre-existing
        // illustrative demo dataset (App\Support\PtoMockData::seedUsers(),
        // seeded by UserSeeder) includes LGU/Establishment accounts whose
        // mock "assignment" doesn't always resolve to a real seeded
        // Listing/Municipality row (e.g. "Aliwagwag Eco-Lodge" was never an
        // actual seeded establishment) — a strict CHECK there would break
        // `migrate:fresh --seed` on data this migration doesn't own. The
        // full rule is enforced authoritatively at the validation/service
        // layer instead (Pto\UsersController, Lgu\UsersController — every
        // real write path always sets both fields correctly for the role),
        // plus EnsureLguHasMunicipality / ownListing() gate page access for
        // any account that still lacks its scope.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE users ADD CONSTRAINT users_pto_has_no_scope_check CHECK (
                    role IS DISTINCT FROM 'pto_administrator'
                    OR (municipality_id IS NULL AND establishment_id IS NULL)
                )
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_pto_has_no_scope_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('establishment_id');
            $table->dropConstrainedForeignId('municipality_id');
        });
    }
};
