<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketType extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'type',
        'price_xof',
        'fee_pass_through_pct',
        'max_qty',
        'per_order_limit',
        'sales_start',
        'sales_end',
        'refundable',
        'archived_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_xof' => 'decimal:2',
            'fee_pass_through_pct' => 'decimal:2',
            'sales_start' => 'datetime',
            'sales_end' => 'datetime',
            'refundable' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the event that owns this ticket type.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Check if the ticket type is available for sale.
     */
    public function isAvailable(): bool
    {
        $now = now();

        // Check if archived
        if ($this->archived_at) {
            return false;
        }

        // Check sales window
        if ($this->sales_start && $now->lt($this->sales_start)) {
            return false; // Not started
        }

        if ($this->sales_end && $now->gt($this->sales_end)) {
            return false; // Ended
        }

        // Check inventory (if max_qty set)
        // TODO: When orders are implemented, check sold count
        // For now, assume available if inventory limit not set
        // if ($this->max_qty !== null) {
        //     $soldCount = $this->orders()->sum('quantity');
        //     if ($soldCount >= $this->max_qty) {
        //         return false;
        //     }
        // }

        return true;
    }

    /**
     * Get the availability status as a human-readable string.
     */
    public function getAvailabilityStatusAttribute(): string
    {
        $now = now();

        if ($this->archived_at) {
            return 'Archived';
        }

        if ($this->sales_start && $now->lt($this->sales_start)) {
            return 'Sales Not Started';
        }

        if ($this->sales_end && $now->gt($this->sales_end)) {
            return 'Sales Ended';
        }

        // TODO: Check if sold out when orders implemented
        // if ($this->max_qty !== null) {
        //     $soldCount = $this->orders()->sum('quantity');
        //     if ($soldCount >= $this->max_qty) {
        //         return 'Sold Out';
        //     }
        // }

        return 'Available';
    }

    /**
     * Get remaining quantity available for sale.
     * Returns null if unlimited.
     */
    public function getRemainingQuantityAttribute(): ?int
    {
        if ($this->max_qty === null) {
            return null; // Unlimited
        }

        // TODO: When orders are implemented, calculate actual remaining
        // $soldCount = $this->orders()->sum('quantity');
        // return max(0, $this->max_qty - $soldCount);

        // For now, return max_qty as placeholder
        return $this->max_qty;
    }

    /**
     * Check if the ticket type can be updated.
     * After sales start, only certain fields can be updated.
     */
    public function canBeUpdated(): bool
    {
        // Cannot update archived tickets
        if ($this->archived_at) {
            return false;
        }

        return true;
    }

    /**
     * Check if sales have started.
     */
    public function hasSalesStarted(): bool
    {
        if (! $this->sales_start) {
            return true; // No start time means available immediately
        }

        return now()->gte($this->sales_start);
    }

    /**
     * Soft delete (archive) this ticket type.
     */
    public function archive(): bool
    {
        $this->archived_at = now();

        return $this->save();
    }

    /**
     * Restore an archived ticket type.
     */
    public function unarchive(): bool
    {
        $this->archived_at = null;

        return $this->save();
    }
}
