<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superseded by security_logs/operation_logs (App\Models\SecurityLog,
     * App\Models\OperationLog) — a single flat events table couldn't express
     * the two tables' distinct read-scoping rules (per-municipality/
     * per-establishment visibility) or the operation_logs-specific columns
     * (old_values/new_values/reason). down() recreates the original shape
     * for a clean rollback; it does not restore any dropped rows.
     */
    public function up(): void
    {
        Schema::dropIfExists('audit_logs');
    }

    public function down(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index(['action', 'created_at']);
        });
    }
};
