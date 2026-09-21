<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RBAC-facing FK alongside the existing `municipality` string column
     * (kept as-is — still read by TourismCatalog, the destination-management
     * traits, and every existing view). Backfilled for existing rows by
     * Database\Seeders\RbacScopeBackfillSeeder. `restrictOnDelete` because a
     * municipality with listings attached should never be silently orphaned.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('municipality_id')->nullable()->after('municipality')
                ->constrained('municipalities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('municipality_id');
        });
    }
};
