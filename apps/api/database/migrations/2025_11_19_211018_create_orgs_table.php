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
        Schema::create('orgs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country_code')->default('CI');
            $table->json('kyb_data')->nullable();
            $table->enum('payout_channel', ['orange_mo', 'mtn_momo', 'wave', 'bank'])->nullable();
            $table->string('payout_identifier')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orgs');
    }
};
