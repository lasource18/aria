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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->onDelete('cascade');
            $table->string('name', 100);
            $table->enum('type', ['free', 'paid', 'donation'])->default('paid');
            $table->decimal('price_xof', 12, 2)->default(0);
            $table->decimal('fee_pass_through_pct', 5, 2)->default(0);
            $table->integer('max_qty')->nullable(); // null = unlimited
            $table->integer('per_order_limit')->default(10);
            $table->timestamp('sales_start')->nullable();
            $table->timestamp('sales_end')->nullable();
            $table->boolean('refundable')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('event_id', 'idx_ticket_types_event_id');
        });

        // Add constraint: price_xof must be >= 0
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT check_ticket_price CHECK (price_xof >= 0)');

        // Add constraint: max_qty must be > 0 if set
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT check_ticket_max_qty CHECK (max_qty IS NULL OR max_qty > 0)');

        // Add constraint: per_order_limit must be > 0
        DB::statement('ALTER TABLE ticket_types ADD CONSTRAINT check_ticket_per_order_limit CHECK (per_order_limit > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
