<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketTypeController extends Controller
{
    /**
     * Display a listing of ticket types for an event.
     */
    public function index(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $ticketTypes = $event->ticketTypes()
            ->whereNull('archived_at')
            ->get();

        return response()->json([
            'data' => $ticketTypes,
        ]);
    }

    /**
     * Store a newly created ticket type.
     */
    public function store(StoreTicketTypeRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $ticketType = $event->ticketTypes()->create($request->validated());

        return response()->json([
            'data' => $ticketType,
            'message' => 'Ticket type created successfully',
        ], 201);
    }

    /**
     * Update the specified ticket type.
     */
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType): JsonResponse
    {
        Gate::authorize('update', $event);

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return response()->json([
                'message' => 'Ticket type not found for this event',
            ], 404);
        }

        if (!$ticketType->canBeUpdated()) {
            return response()->json([
                'message' => 'Cannot update archived ticket type',
            ], 422);
        }

        $ticketType->update($request->validated());

        return response()->json([
            'data' => $ticketType,
            'message' => 'Ticket type updated successfully',
        ]);
    }

    /**
     * Soft delete (archive) the specified ticket type.
     */
    public function destroy(Event $event, TicketType $ticketType): JsonResponse
    {
        Gate::authorize('update', $event);

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return response()->json([
                'message' => 'Ticket type not found for this event',
            ], 404);
        }

        $ticketType->archive();

        return response()->json([
            'message' => 'Ticket type archived successfully',
        ]);
    }
}
