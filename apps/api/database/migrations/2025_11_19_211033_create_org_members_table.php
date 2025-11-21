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
        Schema::create('org_members', function (Blueprint $table) {
            if (DB::getDriverName() === 'pgsql') {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            } else {
                $table->uuid('id')->primary();
            }
            $table->uuid('org_id');
            $table->uuid('user_id');
            $table->enum('role', ['owner', 'admin', 'staff', 'finance']);
            $table->timestamps();

            // Foreign keys
            $table->foreign('org_id')->references('id')->on('orgs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint: user can only have one role per org
            $table->unique(['org_id', 'user_id']);

            // Indexes
            $table->index('org_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_members');
    }
};
