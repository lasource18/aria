<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            if (DB::getDriverName() === 'pgsql') {
                $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            } else {
                $table->uuid('id')->primary();
            }
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('org_id')->nullable()->constrained('orgs')->cascadeOnDelete();
            $table->string('action'); // e.g., 'org.created', 'user.logged_in'
            $table->string('entity_type'); // e.g., 'Org', 'User', 'OrgMember'
            $table->uuid('entity_id'); // polymorphic reference
            // Use json for compatibility (jsonb is PostgreSQL-specific)
            $table->json('changes')->nullable(); // before/after state
            $table->json('metadata')->nullable(); // additional context
            $table->string('ip_address'); // IPv4 or IPv6
            $table->text('user_agent')->nullable();
            // Use timestamp for compatibility (timestampTz is PostgreSQL-specific)
            $table->timestamp('created_at'); // immutable - no updated_at

            // Indexes for efficient querying
            $table->index(['user_id', 'created_at']);
            $table->index(['org_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
