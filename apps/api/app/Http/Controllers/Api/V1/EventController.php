<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Org;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventController extends BaseController
{
    /**
     * Display a listing of published events (public discovery).
     * Supports full-text search, category, date range, geospatial, and price filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::where('status', 'published');

        // Full-text search using pg_trgm
        if ($q = $request->input('q')) {
            $query->whereRaw('similarity(title, ?) > 0.3', [$q])
                ->orderByRaw('similarity(title, ?) DESC', [$q]);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Date range filters
        if ($from = $request->input('from')) {
            $query->where('start_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('start_at', '<=', $to);
        }

        // Geospatial filter (50km radius)
        if ($request->has('lat') && $request->has('lng')) {
            $lat = $request->input('lat');
            $lng = $request->input('lng');
            $radiusKm = $request->input('radius', 50); // Default 50km

            try {
                // Try PostGIS
                $query->whereRaw(
                    'ST_DWithin(location, ST_MakePoint(?, ?)::geography, ?)',
                    [$lng, $lat, $radiusKm * 1000]
                );
            } catch (\Exception $e) {
                // Fallback to bounding box query with lat/lng columns
                $latDelta = $radiusKm / 111; // Approximate km to degrees
                $lngDelta = $radiusKm / (111 * cos(deg2rad($lat)));

                $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                    ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
            }
        }

        // Price filter
        if ($price = $request->input('price')) {
            if ($price === 'free') {
                $query->whereHas('ticketTypes', function ($q) {
                    $q->where('type', 'free')->whereNull('archived_at');
                });
            } elseif ($price === 'paid') {
                $query->whereHas('ticketTypes', function ($q) {
                    $q->where('type', 'paid')->whereNull('archived_at');
                });
            }
        }

        // Default sort: upcoming events first (if not searching)
        if (! $request->has('q')) {
            $query->orderBy('start_at', 'asc');
        }

        $events = $query->with(['ticketTypes' => function ($q) {
            $q->whereNull('archived_at');
        }, 'org:id,name,slug'])
            ->paginate(20);

        return $this->paginatedResponse($events);
    }

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request, Org $org): JsonResponse
    {
        Gate::authorize('create', [Event::class, $org]);

        $event = $org->events()->create($request->validated());
        $event->refresh(); // Refresh to get database defaults like status

        return $this->successResponse(
            $event->load('ticketTypes', 'org:id,name,slug'),
            ['message' => 'Event created successfully']
        )->setStatusCode(201);
    }

    /**
     * Display the specified event by slug.
     */
    public function show(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $event->load(['ticketTypes' => function ($q) {
            $q->whereNull('archived_at');
        }, 'org:id,name,slug']);

        return $this->successResponse($event);
    }

    /**
     * Update the specified event.
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $event->update($request->validated());

        return $this->successResponse(
            $event->load('ticketTypes', 'org:id,name,slug'),
            ['message' => 'Event updated successfully']
        );
    }

    /**
     * Remove the specified event (only draft events).
     */
    public function destroy(Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);

        // Log before deletion
        AuditLog::create([
            'user_id' => auth()->id(),
            'org_id' => $event->org_id,
            'action' => 'event.deleted',
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'changes' => null,
            'metadata' => ['event_title' => $event->title, 'event_status' => $event->status],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $event->delete();

        return $this->successResponse(
            null,
            ['message' => 'Event deleted successfully']
        );
    }

    /**
     * Publish an event (draft → published).
     * Requires at least one ticket type.
     */
    public function publish(Event $event): JsonResponse
    {
        Gate::authorize('publish', $event);

        if (! $event->canBePublished()) {
            return $this->errorResponse(
                'PUBLISH_REQUIRES_TICKETS',
                'Cannot publish event without at least one ticket type',
                null,
                422
            );
        }

        $event->publish();

        AuditLog::create([
            'user_id' => auth()->id(),
            'org_id' => $event->org_id,
            'action' => 'event.published',
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'changes' => ['old_status' => 'draft', 'new_status' => 'published'],
            'metadata' => ['event_title' => $event->title],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->successResponse(
            $event->load('ticketTypes', 'org:id,name,slug'),
            ['message' => 'Event published successfully']
        );
    }

    /**
     * Cancel an event (published → canceled).
     * Triggers refund workflow for paid tickets (if implemented).
     */
    public function cancel(Event $event): JsonResponse
    {
        Gate::authorize('cancel', $event);

        $event->cancel();

        AuditLog::create([
            'user_id' => auth()->id(),
            'org_id' => $event->org_id,
            'action' => 'event.canceled',
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'changes' => ['old_status' => 'published', 'new_status' => 'canceled'],
            'metadata' => ['event_title' => $event->title],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->successResponse(
            $event->load('ticketTypes', 'org:id,name,slug'),
            ['message' => 'Event canceled successfully']
        );
    }
}
