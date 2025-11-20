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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('org_id')->constrained('orgs')->onDelete('cascade');
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('description_md');
            $table->enum('category', ['music', 'arts', 'sports', 'tech', 'other'])->default('other');
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            // PostGIS geography column for lat/lng - created via raw SQL below
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('timezone', 50)->default('Africa/Abidjan');
            $table->enum('status', ['draft', 'published', 'canceled', 'ended'])->default('draft');
            $table->boolean('is_online')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('org_id', 'idx_events_org_id');
            $table->index('start_at', 'idx_events_start_at');
            $table->index('status', 'idx_events_status');
        });

        // Check if PostGIS is available
        $postgisAvailable = false;
        try {
            $result = DB::selectOne("SELECT COUNT(*) as count FROM pg_available_extensions WHERE name = 'postgis' AND installed_version IS NOT NULL");
            $postgisAvailable = $result && $result->count > 0;
        } catch (\Exception $e) {
            $postgisAvailable = false;
        }

        if ($postgisAvailable) {
            // Add PostGIS geography column for location (POINT)
            DB::statement('ALTER TABLE events ADD COLUMN location GEOGRAPHY(POINT, 4326)');
            // Add GiST index for geospatial queries
            DB::statement('CREATE INDEX idx_events_location ON events USING gist(location)');
        } else {
            // PostGIS not available, add decimal lat/lng columns as fallback
            Schema::table('events', function (Blueprint $table) {
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->index(['latitude', 'longitude'], 'idx_events_lat_lng');
            });
        }

        // Add GIN index for full-text search using pg_trgm (if available)
        try {
            $result = DB::selectOne("SELECT COUNT(*) as count FROM pg_available_extensions WHERE name = 'pg_trgm' AND installed_version IS NOT NULL");
            if ($result && $result->count > 0) {
                DB::statement('CREATE INDEX idx_events_title_trgm ON events USING gin(title gin_trgm_ops)');
            }
        } catch (\Exception $e) {
            // pg_trgm not available, skip index
        }

        // Add partial index for published events (most common query)
        DB::statement("CREATE INDEX idx_events_status_published ON events(status) WHERE status = 'published'");

        // Add constraint: start_at must be before end_at
        DB::statement('ALTER TABLE events ADD CONSTRAINT check_event_dates CHECK (start_at < end_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
