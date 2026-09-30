<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business/data actions — append-only (see App\Models\OperationLog).
     * `entity_id` has no FK constraint: `entity_type` names one of several
     * unrelated tables (establishments, destinations, municipal reports,
     * ...), the same polymorphic-by-convention shape already used by
     * security_logs/the old audit_logs (target_type/target_id). `reason` is
     * required at the application layer (OperationLogger), not the DB, for
     * return/reject/unlock/delete actions.
     */
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('user_role', 30);
            $table->string('action', 30);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->nullOnDelete();
            $table->foreignId('establishment_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['municipality_id', 'created_at']);
            $table->index(['establishment_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
