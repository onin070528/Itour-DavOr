<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports the Official Report format: `verification_code` backs the
     * public verification page; `revision_number`/`supersedes_id` let an
     * LGU resubmission reopen a Verified report as a brand NEW row instead
     * of overwriting it in place, so a Verified report's own row never
     * changes under it (see Lgu\MonthlyReportsController::consolidate());
     * `frozen_snapshot` is the exact breakdown/totals/comparison data
     * captured the moment a report is Verified, so its PDF always renders
     * from that snapshot rather than live (and therefore mutable) data.
     */
    public function up(): void
    {
        Schema::table('municipal_reports', function (Blueprint $table) {
            $table->string('verification_code')->nullable()->unique()->after('status');
            $table->unsignedInteger('revision_number')->default(1)->after('verification_code');
            $table->foreignId('supersedes_id')->nullable()->after('revision_number')
                ->constrained('municipal_reports')->nullOnDelete();
            $table->json('frozen_snapshot')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('municipal_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn(['verification_code', 'revision_number', 'frozen_snapshot']);
        });
    }
};
