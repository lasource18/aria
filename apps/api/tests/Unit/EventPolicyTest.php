<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Org;
use App\Models\OrgMember;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test published event is viewable publicly.
     */
    public function test_published_event_viewable_publicly(): void
    {
        $policy = new EventPolicy;
        $event = Event::factory()->published()->create();

        // Guest user (null) can view published event
        $this->assertTrue($policy->view(null, $event));

        // Authenticated user can view published event
        $user = User::factory()->create();
        $this->assertTrue($policy->view($user, $event));
    }

    /**
     * Test draft event is viewable by org members only.
     */
    public function test_draft_event_viewable_by_org_members_only(): void
    {
        $policy = new EventPolicy;
        $org = Org::factory()->create();
        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        // Guest user cannot view draft event
        $this->assertFalse($policy->view(null, $event));

        // Non-member cannot view draft event
        $nonMember = User::factory()->create();
        $this->assertFalse($policy->view($nonMember, $event));

        // Org member can view draft event
        $member = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'staff',
        ]);

        $this->assertTrue($policy->view($member, $event));
    }

    /**
     * Test only owner/admin/staff can update event.
     */
    public function test_only_owner_admin_staff_can_update_event(): void
    {
        $policy = new EventPolicy;
        $org = Org::factory()->create();
        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        // Non-member cannot update
        $nonMember = User::factory()->create();
        $this->assertFalse($policy->update($nonMember, $event));

        // Staff can update
        $staff = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $staff->id,
            'role' => 'staff',
        ]);
        $this->assertTrue($policy->update($staff, $event));

        // Admin can update
        $admin = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        $this->assertTrue($policy->update($admin, $event));

        // Owner can update
        $owner = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertTrue($policy->update($owner, $event));

        // Cannot update canceled event
        $canceledEvent = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'canceled',
        ]);
        $this->assertFalse($policy->update($owner, $canceledEvent));
    }

    /**
     * Test only owner can delete event.
     */
    public function test_only_owner_can_delete_event(): void
    {
        $policy = new EventPolicy;
        $org = Org::factory()->create();
        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        // Non-member cannot delete
        $nonMember = User::factory()->create();
        $this->assertFalse($policy->delete($nonMember, $event));

        // Staff cannot delete
        $staff = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $staff->id,
            'role' => 'staff',
        ]);
        $this->assertFalse($policy->delete($staff, $event));

        // Admin cannot delete
        $admin = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        $this->assertFalse($policy->delete($admin, $event));

        // Owner can delete draft event
        $owner = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertTrue($policy->delete($owner, $event));

        // Cannot delete published event
        $publishedEvent = Event::factory()->published()->create([
            'org_id' => $org->id,
        ]);
        $this->assertFalse($policy->delete($owner, $publishedEvent));
    }
}
