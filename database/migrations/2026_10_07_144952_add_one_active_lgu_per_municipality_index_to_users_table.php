<?php

/*
 * System     : iTOUR - Integrated Tourism Information and Monitoring System
 * Purpose    : Allows at most one active LGU Tourism Admin account per municipality/city.
 * Programmer : <name(s)>
 * Copyright  : 2026 University of Mindanao. All rights reserved.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX_NAME = 'users_one_active_lgu_per_municipality_unique';

    /**
     * Partial unique index — only LGU rows that aren't Inactive count, so
     * establishment accounts can share a municipality with their LGU, and
     * PTO can replace an LGU admin by deactivating the old account first.
     * Supported by both PostgreSQL and SQLite (the test driver). Refuses to
     * run, naming the conflicting municipality ids, rather than failing
     * with a bare SQL error when duplicates already exist.
     */
    public function up(): void
    {
        $arrConflictingMunicipalityIds = DB::table('tbl_users')
            ->where('usr_role', 'lgu')
            ->where('usr_status', '!=', 'Inactive')
            ->whereNotNull('mun_id')
            ->groupBy('mun_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('mun_id')
            ->all();

        if ($arrConflictingMunicipalityIds !== []) {
            throw new RuntimeException(
                'Cannot add '.self::INDEX_NAME.': municipality id(s) '.implode(', ', $arrConflictingMunicipalityIds)
                .' have more than one active LGU account. Deactivate the extra account(s) first '
                .'(php artisan db:seed --class=UserSeeder deactivates the legacy demo accounts).'
            );
        }

        DB::statement('CREATE UNIQUE INDEX '.self::INDEX_NAME." ON tbl_users (mun_id) WHERE usr_role = 'lgu' AND usr_status <> 'Inactive'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
    }
};
