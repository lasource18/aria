<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Org;
use App\Models\OrgMember;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create event for owned organization.
     */
    public function test_user_can_create_event_for_owned_org(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/orgs/{$org->id}/events", [
                'title' => 'Test Event',
                'description_md' => 'Test description',
                'category' => 'music',
                'venue_name' => 'Test Venue',
                'venue_address' => '123 Test St',
                'location' => ['lat' => 5.3196, 'lng' => -4.0156],
                'start_at' => now()->addDays(7)->toISOString(),
                'end_at' => now()->addDays(7)->addHours(4)->toISOString(),
                'timezone' => 'Africa/Abidjan',
                'is_online' => false,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Test Event');
        $response->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('events', [
            'title' => 'Test Event',
            'org_id' => $org->id,
            'status' => 'draft',
        ]);
    }

    /**
     * Test event slug is auto-generated from title.
     */
    public function test_event_slug_auto_generated_from_title(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/orgs/{$org->id}/events", [
                'title' => 'Amazing Jazz Festival',
                'description_md' => 'Test description',
                'category' => 'music',
                'start_at' => now()->addDays(7)->toISOString(),
                'end_at' => now()->addDays(7)->addHours(4)->toISOString(),
                'timezone' => 'Africa/Abidjan',
                'is_online' => true,
            ]);

        $response->assertStatus(201);
        $event = Event::where('title', 'Amazing Jazz Festival')->first();
        $this->assertEquals('amazing-jazz-festival', $event->slug);
    }

    /**
     * Test event slug handles duplicates with random suffix.
     */
    public function test_event_slug_handles_duplicates_with_random_suffix(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Create first event
        Event::factory()->create([
            'org_id' => $org->id,
            'title' => 'Duplicate Event',
            'slug' => 'duplicate-event',
        ]);

        // Create second event with same title
        $response = $this->actingAs($user)
            ->postJson("/api/v1/orgs/{$org->id}/events", [
                'title' => 'Duplicate Event',
                'description_md' => 'Test description',
                'category' => 'music',
                'start_at' => now()->addDays(7)->toISOString(),
                'end_at' => now()->addDays(7)->addHours(4)->toISOString(),
                'timezone' => 'Africa/Abidjan',
                'is_online' => true,
            ]);

        $response->assertStatus(201);
        $newEvent = Event::where('title', 'Duplicate Event')
            ->where('slug', '!=', 'duplicate-event')
            ->first();

        $this->assertNotNull($newEvent);
        $this->assertStringStartsWith('duplicate-event-', $newEvent->slug);
        $this->assertNotEquals('duplicate-event', $newEvent->slug);
    }

    /**
     * Test only owner/admin/staff can create event.
     */
    public function test_only_owner_admin_staff_can_create_event(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        // User is not a member

        $response = $this->actingAs($user)
            ->postJson("/api/v1/orgs/{$org->id}/events", [
                'title' => 'Test Event',
                'description_md' => 'Test description',
                'category' => 'music',
                'start_at' => now()->addDays(7)->toISOString(),
                'end_at' => now()->addDays(7)->addHours(4)->toISOString(),
                'timezone' => 'Africa/Abidjan',
                'is_online' => true,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test user can update own event.
     */
    public function test_user_can_update_own_event(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]);

        $event = Event::factory()->create([
            'org_id' => $org->id,
            'title' => 'Original Title',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/events/{$event->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
        $this->assertEquals('Updated Title', $event->fresh()->title);
    }

    /**
     * Test user cannot update other org's event.
     */
    public function test_user_cannot_update_other_org_event(): void
    {
        $user = User::factory()->create();
        $otherOrg = Org::factory()->create();
        $event = Event::factory()->create([
            'org_id' => $otherOrg->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/events/{$event->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test user can delete draft event.
     */
    public function test_user_can_delete_draft_event(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', [
            'id' => $event->id,
        ]);
    }

    /**
     * Test cannot delete published event.
     */
    public function test_cannot_delete_published_event(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $event = Event::factory()->published()->create([
            'org_id' => $org->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
        ]);
    }

    /**
     * Test can publish event with ticket types.
     */
    public function test_can_publish_event_with_ticket_types(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]);

        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        // Add ticket type
        TicketType::factory()->create([
            'event_id' => $event->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/publish");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'published');
        $this->assertEquals('published', $event->fresh()->status);
    }

    /**
     * Test cannot publish event without ticket types.
     */
    public function test_cannot_publish_event_without_ticket_types(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]);

        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/publish");

        $response->assertStatus(422);
        $this->assertEquals('draft', $event->fresh()->status);
    }

    /**
     * Test publish changes status to published.
     */
    public function test_publish_changes_status_to_published(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        TicketType::factory()->create(['event_id' => $event->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/publish");

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published',
        ]);
    }

    /**
     * Test can cancel published event.
     */
    public function test_can_cancel_published_event(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        $event = Event::factory()->published()->create([
            'org_id' => $org->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'canceled');
        $this->assertEquals('canceled', $event->fresh()->status);
    }

    /**
     * Test cancel changes status to canceled.
     */
    public function test_cancel_changes_status_to_canceled(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $event = Event::factory()->published()->create([
            'org_id' => $org->id,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/cancel");

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'canceled',
        ]);
    }

    /**
     * Test published events are visible publicly.
     */
    public function test_published_events_visible_publicly(): void
    {
        $event = Event::factory()->published()->create();

        // Unauthenticated user can view
        $response = $this->getJson("/api/v1/events/{$event->slug}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $event->id);
    }

    /**
     * Test draft events only visible to org members.
     */
    public function test_draft_events_only_visible_to_org_members(): void
    {
        $org = Org::factory()->create();
        $event = Event::factory()->create([
            'org_id' => $org->id,
            'status' => 'draft',
        ]);

        // Unauthenticated user cannot view
        $response = $this->getJson("/api/v1/events/{$event->slug}");
        $response->assertStatus(403);

        // Non-member cannot view
        $nonMember = User::factory()->create();
        $response = $this->actingAs($nonMember)
            ->getJson("/api/v1/events/{$event->slug}");
        $response->assertStatus(403);

        // Org member can view
        $member = User::factory()->create();
        OrgMember::factory()->create([
            'org_id' => $org->id,
            'user_id' => $member->id,
            'role' => 'staff',
        ]);

        $response = $this->actingAs($member)
            ->getJson("/api/v1/events/{$event->slug}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $event->id);
    }
}
