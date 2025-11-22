<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'title',
        'slug',
        'description_md',
        'category',
        'venue_name',
        'venue_address',
        // location handled by accessor/mutator
        'latitude',
        'longitude',
        'start_at',
        'end_at',
        'timezone',
        'status',
        'is_online',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_online' => 'boolean',
            'settings' => 'array',
            'status' => 'string',
        ];
    }

    /**
     * Boot the model and auto-generate slug.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->title);
            }
        });
    }

    /**
     * Generate a unique slug from the title.
     * Appends a random 4-character suffix if slug already exists.
     */
    protected static function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;

        // Add 4-char random suffix if duplicate
        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    /**
     * Set the location attribute from lat/lng array.
     * Stores in lat/lng columns which will be converted to PostGIS if needed after save.
     */
    public function setLocationAttribute($value): void
    {
        if (is_array($value) && isset($value['lat'], $value['lng'])) {
            $this->attributes['latitude'] = $value['lat'];
            $this->attributes['longitude'] = $value['lng'];
        } elseif (is_null($value)) {
            $this->attributes['latitude'] = null;
            $this->attributes['longitude'] = null;
        }
    }

    /**
     * Boot method to handle PostGIS conversion after save.
     */
    protected static function booted()
    {
        static::saved(function ($event) {
            // Check if we have lat/lng and PostGIS is available
            if ($event->latitude && $event->longitude) {
                try {
                    // Try to update PostGIS location column
                    DB::statement(
                        "UPDATE events SET location = ST_GeogFromText('POINT(? ?)') WHERE id = ?",
                        [$event->longitude, $event->latitude, $event->id]
                    );
                } catch (\Exception $e) {
                    // PostGIS not available, lat/lng columns are sufficient
                }
            }
        });
    }

    /**
     * Get the location attribute as lat/lng array.
     * Converts from PostGIS GEOGRAPHY(POINT) or fallback lat/lng columns.
     */
    public function getLocationAttribute($value): ?array
    {
        // Try PostGIS first
        if ($value && $this->id) {
            try {
                $result = DB::selectOne(
                    'SELECT ST_Y(location::geometry) as lat, ST_X(location::geometry) as lng FROM events WHERE id = ?',
                    [$this->id]
                );

                return $result ? ['lat' => (float) $result->lat, 'lng' => (float) $result->lng] : null;
            } catch (\Exception $e) {
                // Fall through to fallback
            }
        }

        // Fallback to separate columns
        if (isset($this->attributes['latitude']) && isset($this->attributes['longitude'])) {
            return [
                'lat' => (float) $this->attributes['latitude'],
                'lng' => (float) $this->attributes['longitude'],
            ];
        }

        return null;
    }

    /**
     * Get the organization that owns this event.
     */
    public function org(): BelongsTo
    {
        return $this->belongsTo(Org::class);
    }

    /**
     * Get the ticket types for this event.
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    /**
     * Check if the event can be published.
     * Requires at least one ticket type.
     */
    public function canBePublished(): bool
    {
        return $this->ticketTypes()->count() > 0;
    }

    /**
     * Check if the event can be canceled.
     * Only published events can be canceled.
     */
    public function canBeCanceled(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Check if the event can be updated.
     * Cannot update canceled or ended events.
     */
    public function canBeUpdated(): bool
    {
        return ! in_array($this->status, ['canceled', 'ended']);
    }

    /**
     * Check if the event can be deleted.
     * Only draft events can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Publish the event (draft → published).
     */
    public function publish(): bool
    {
        if ($this->status !== 'draft' || ! $this->canBePublished()) {
            return false;
        }

        $this->status = 'published';

        return $this->save();
    }

    /**
     * Cancel the event (published → canceled).
     */
    public function cancel(): bool
    {
        if (! $this->canBeCanceled()) {
            return false;
        }

        $this->status = 'canceled';

        return $this->save();
    }

    /**
     * Get the minimum ticket price for this event.
     */
    public function getMinTicketPriceAttribute(): ?float
    {
        $minPrice = $this->ticketTypes()
            ->where('type', 'paid')
            ->where('archived_at', null)
            ->min('price_xof');

        return $minPrice ? (float) $minPrice : null;
    }

    /**
     * Get the maximum ticket price for this event.
     */
    public function getMaxTicketPriceAttribute(): ?float
    {
        $maxPrice = $this->ticketTypes()
            ->where('type', 'paid')
            ->where('archived_at', null)
            ->max('price_xof');

        return $maxPrice ? (float) $maxPrice : null;
    }

    /**
     * Check if the event has free tickets.
     */
    public function hasFreeTickets(): bool
    {
        return $this->ticketTypes()
            ->where('type', 'free')
            ->where('archived_at', null)
            ->exists();
    }
}
