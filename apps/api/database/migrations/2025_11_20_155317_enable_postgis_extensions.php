<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable PostGIS extension for geospatial data (if available)
        try {
            DB::unprepared('CREATE EXTENSION IF NOT EXISTS postgis;');
            Log::info('PostGIS extension enabled successfully');
        } catch (\Exception $e) {
            Log::warning('PostGIS extension not available: '.$e->getMessage());
            // Continue - will use fallback approach for location storage
        }

        // Enable pg_trgm extension for full-text search with trigram matching
        try {
            DB::unprepared('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            Log::info('pg_trgm extension enabled successfully');
        } catch (\Exception $e) {
            Log::warning('pg_trgm extension not available: '.$e->getMessage());
            // Continue - full-text search will be limited
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm;');
        DB::statement('DROP EXTENSION IF EXISTS postgis;');
    }
};
