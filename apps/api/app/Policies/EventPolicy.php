<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Org;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view any models.
     * All users can view published events.
     */
    public function viewAny(?User $user): bool
    {
        return true; // Public endpoint for discovery
    }

    /**
     * Determine whether the user can view the model.
     * Published events are public; draft events require org membership.
     */
    public function view(?User $user, Event $event): bool
    {
        // Published events are public
        if ($event->status === 'published') {
            return true;
        }

        // Draft events only visible to org members
        if (! $user) {
            return false;
        }

        return $event->org->hasMember($user);
    }

    /**
     * Determine whether the user can create events in an organization.
     * Requires staff role or higher (staff, admin, owner).
     */
    public function create(User $user, Org $org): bool
    {
        return $org->hasMemberWithRole($user, ['owner', 'admin', 'staff']);
    }

    /**
     * Determine whether the user can update the model.
     * Requires staff role or higher in the event's organization.
     */
    public function update(User $user, Event $event): bool
    {
        // Cannot update canceled or ended events
        if (! $event->canBeUpdated()) {
            return false;
        }

        return $event->org->hasMemberWithRole($user, ['owner', 'admin', 'staff']);
    }

    /**
     * Determine whether the user can delete the model.
     * Only draft events can be deleted, and only by owner.
     */
    public function delete(User $user, Event $event): bool
    {
        if (! $event->canBeDeleted()) {
            return false;
        }

        return $event->org->hasMemberWithRole($user, ['owner']);
    }

    /**
     * Determine whether the user can publish the event.
     * Requires staff role or higher.
     */
    public function publish(User $user, Event $event): bool
    {
        if ($event->status !== 'draft') {
            return false;
        }

        return $event->org->hasMemberWithRole($user, ['owner', 'admin', 'staff']);
    }

    /**
     * Determine whether the user can cancel the event.
     * Requires admin role or higher.
     */
    public function cancel(User $user, Event $event): bool
    {
        if (! $event->canBeCanceled()) {
            return false;
        }

        return $event->org->hasMemberWithRole($user, ['owner', 'admin']);
    }
}
