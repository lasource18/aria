<?php

namespace App\Policies;

use App\Models\Org;
use App\Models\User;

class OrgPolicy
{
    /**
     * Determine whether the user can view any organizations.
     */
    public function viewAny(User $user): bool
    {
        // Users can view their own organizations
        return true;
    }

    /**
     * Determine whether the user can view the organization.
     */
    public function view(User $user, Org $org): bool
    {
        // User must be a member of the org or be a platform admin
        return $org->hasMember($user) || $user->isPlatformAdmin();
    }

    /**
     * Determine whether the user can create organizations.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create an organization
        return true;
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update(User $user, Org $org): bool
    {
        // Only owner or admin can update
        return $org->hasMemberWithRole($user, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(User $user, Org $org): bool
    {
        // Only owner can delete
        return $org->hasMemberWithRole($user, ['owner']);
    }

    /**
     * Determine whether the user can manage members.
     */
    public function manageMembers(User $user, Org $org): bool
    {
        // Only owner or admin can manage members
        return $org->hasMemberWithRole($user, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can add members.
     */
    public function addMember(User $user, Org $org): bool
    {
        // Only owner or admin can add members
        return $org->hasMemberWithRole($user, ['owner', 'admin']);
    }

    /**
     * Determine whether the user can remove members.
     */
    public function removeMember(User $user, Org $org): bool
    {
        // Only owner can remove members
        return $org->hasMemberWithRole($user, ['owner']);
    }

    /**
     * Determine whether the user can update member roles.
     */
    public function updateMemberRole(User $user, Org $org): bool
    {
        // Only owner can change roles
        return $org->hasMemberWithRole($user, ['owner']);
    }

    /**
     * Determine whether the user can view financials.
     */
    public function viewFinancials(User $user, Org $org): bool
    {
        // Owner, admin, and finance roles can view financials
        return $org->hasMemberWithRole($user, ['owner', 'admin', 'finance']);
    }

    /**
     * Determine whether the user can initiate payouts.
     */
    public function initiatePayout(User $user, Org $org): bool
    {
        // Only owner and finance roles can initiate payouts
        return $org->hasMemberWithRole($user, ['owner', 'finance']);
    }
}
