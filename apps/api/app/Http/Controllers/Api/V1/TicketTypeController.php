<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketTypeController extends BaseController
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

        return $this->successResponse($ticketTypes);
    }

    /**
     * Store a newly created ticket type.
     */
    public function store(StoreTicketTypeRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $ticketType = $event->ticketTypes()->create($request->validated());

        return $this->successResponse(
            $ticketType,
            ['message' => 'Ticket type created successfully']
        )->setStatusCode(201);
    }

    /**
     * Update the specified ticket type.
     */
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType): JsonResponse
    {
        Gate::authorize('update', $event);

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return $this->errorResponse(
                'TICKET_TYPE_NOT_FOUND',
                'Ticket type not found for this event',
                null,
                404
            );
        }

        if (! $ticketType->canBeUpdated()) {
            return $this->errorResponse(
                'CANNOT_UPDATE_ARCHIVED',
                'Cannot update archived ticket type',
                null,
                422
            );
        }

        $ticketType->update($request->validated());

        return $this->successResponse(
            $ticketType,
            ['message' => 'Ticket type updated successfully']
        );
    }

    /**
     * Soft delete (archive) the specified ticket type.
     */
    public function destroy(Event $event, TicketType $ticketType): JsonResponse
    {
        Gate::authorize('update', $event);

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return $this->errorResponse(
                'TICKET_TYPE_NOT_FOUND',
                'Ticket type not found for this event',
                null,
                404
            );
        }

        $ticketType->archive();

        AuditLog::create([
            'user_id' => auth()->id(),
            'org_id' => $event->org_id,
            'action' => 'ticket_type.archived',
            'entity_type' => 'ticket_type',
            'entity_id' => $ticketType->id,
            'changes' => null,
            'metadata' => [
                'ticket_type_name' => $ticketType->name,
                'event_id' => $event->id,
                'event_title' => $event->title,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->successResponse(
            null,
            ['message' => 'Ticket type archived successfully']
        );
    }
}
