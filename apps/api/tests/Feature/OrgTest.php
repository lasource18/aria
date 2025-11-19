<?php

namespace Tests\Feature;

use App\Models\Org;
use App\Models\OrgMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test creating an organization automatically assigns creator as owner.
     */
    public function test_creating_org_assigns_creator_as_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/orgs', [
                'name' => 'Test Organization',
                'country_code' => 'CI',
            ]);

        $response->assertStatus(201);
        $org = Org::where('name', 'Test Organization')->first();

        $this->assertNotNull($org);
        $this->assertTrue($org->hasMemberWithRole($user->id, 'owner'));
    }

    /**
     * Test listing organizations returns only user's orgs.
     */
    public function test_listing_orgs_returns_only_user_orgs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $userOrg = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $userOrg->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $otherOrg = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $otherOrg->id,
            'user_id' => $otherUser->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/orgs');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $userOrg->id);
    }

    /**
     * Test viewing organization details requires membership.
     */
    public function test_viewing_org_requires_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $otherUser->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/orgs/{$org->id}");

        $response->assertStatus(403);
    }

    /**
     * Test updating organization requires owner or admin role.
     */
    public function test_updating_org_requires_owner_or_admin(): void
    {
        $staffUser = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $staffUser->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)
            ->patchJson("/api/v1/orgs/{$org->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin can update organization.
     */
    public function test_admin_can_update_org(): void
    {
        $adminUser = User::factory()->create();
        $org = Org::factory()->create(['name' => 'Original Name']);
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $adminUser->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($adminUser)
            ->patchJson("/api/v1/orgs/{$org->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $org->fresh()->name);
    }

    /**
     * Test adding a member to organization requires owner or admin role.
     */
    public function test_adding_member_requires_owner_or_admin(): void
    {
        $staffUser = User::factory()->create();
        $newUser = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $staffUser->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)
            ->postJson("/api/v1/orgs/{$org->id}/members", [
                'user_id' => $newUser->id,
                'role' => 'staff',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test owner can add members to organization.
     */
    public function test_owner_can_add_members(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/orgs/{$org->id}/members", [
                'user_id' => $newMember->id,
                'role' => 'staff',
            ]);

        $response->assertStatus(201);
        $this->assertTrue($org->hasMember($newMember->id));
    }

    /**
     * Test cannot add duplicate member to organization.
     */
    public function test_cannot_add_duplicate_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/orgs/{$org->id}/members", [
                'user_id' => $member->id,
                'role' => 'admin',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user_id']);
    }

    /**
     * Test updating member role requires owner or admin.
     */
    public function test_updating_member_role_requires_owner_or_admin(): void
    {
        $staffUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $staffUser->id,
            'role' => 'staff',
        ]);
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $targetUser->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)
            ->patchJson("/api/v1/orgs/{$org->id}/members/{$targetUser->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test owner can update member roles.
     */
    public function test_owner_can_update_member_roles(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $membership = OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($owner)
            ->patchJson("/api/v1/orgs/{$org->id}/members/{$member->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('admin', $membership->fresh()->role);
    }

    /**
     * Test removing member requires owner or admin.
     */
    public function test_removing_member_requires_owner_or_admin(): void
    {
        $staffUser = User::factory()->create();
        $targetUser = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $staffUser->id,
            'role' => 'staff',
        ]);
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $targetUser->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($staffUser)
            ->deleteJson("/api/v1/orgs/{$org->id}/members/{$targetUser->id}");

        $response->assertStatus(403);
    }

    /**
     * Test owner can remove members.
     */
    public function test_owner_can_remove_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/orgs/{$org->id}/members/{$member->id}");

        $response->assertStatus(200);
        $this->assertFalse($org->hasMember($member->id));
    }

    /**
     * Test cannot remove last owner from organization.
     */
    public function test_cannot_remove_last_owner(): void
    {
        $owner = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/orgs/{$org->id}/members/{$owner->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Cannot remove the last owner from the organization.']);
    }

    /**
     * Test slug is automatically generated from organization name.
     */
    public function test_slug_auto_generated_from_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/orgs', [
                'name' => 'Test Organization Name',
                'country_code' => 'CI',
            ]);

        $response->assertStatus(201);
        $org = Org::where('name', 'Test Organization Name')->first();

        $this->assertEquals('test-organization-name', $org->slug);
    }

    /**
     * Test slug uniqueness is enforced with auto-increment.
     */
    public function test_slug_uniqueness_enforced(): void
    {
        $user = User::factory()->create();

        // Create first org
        $this->actingAs($user)
            ->postJson('/api/v1/orgs', [
                'name' => 'Duplicate Name',
                'country_code' => 'CI',
            ]);

        // Create second org with same name
        $response = $this->actingAs($user)
            ->postJson('/api/v1/orgs', [
                'name' => 'Duplicate Name',
                'country_code' => 'CI',
            ]);

        $response->assertStatus(201);
        $orgs = Org::where('name', 'Duplicate Name')->get();

        $this->assertCount(2, $orgs);
        $this->assertEquals('duplicate-name', $orgs[0]->slug);
        $this->assertEquals('duplicate-name-1', $orgs[1]->slug);
    }

    /**
     * Test unauthenticated users cannot access organizations.
     */
    public function test_unauthenticated_cannot_access_orgs(): void
    {
        $response = $this->getJson('/api/v1/orgs');
        $response->assertStatus(401);
    }

    /**
     * Test unauthenticated users cannot create organizations.
     */
    public function test_unauthenticated_cannot_create_org(): void
    {
        $response = $this->postJson('/api/v1/orgs', [
            'name' => 'Test Org',
            'country_code' => 'CI',
        ]);

        $response->assertStatus(401);
    }
}
