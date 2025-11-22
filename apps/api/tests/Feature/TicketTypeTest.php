<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Org;
use App\Models\OrgMember;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test can create ticket type for event.
     */
    public function test_can_create_ticket_type_for_event(): void
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
            ->postJson("/api/v1/events/{$event->id}/ticket-types", [
                'name' => 'General Admission',
                'type' => 'paid',
                'price_xof' => 5000,
                'max_qty' => 100,
                'per_order_limit' => 10,
                'refundable' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'General Admission');
        $response->assertJsonPath('data.type', 'paid');
        $response->assertJsonPath('data.price_xof', '5000.00');

        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'name' => 'General Admission',
            'type' => 'paid',
        ]);
    }

    /**
     * Test ticket type validates price for paid type.
     */
    public function test_ticket_type_validates_price_for_paid_type(): void
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

        // Attempt to create paid ticket with zero price
        $response = $this->actingAs($user)
            ->postJson("/api/v1/events/{$event->id}/ticket-types", [
                'name' => 'Paid Ticket',
                'type' => 'paid',
                'price_xof' => 0,
                'max_qty' => 100,
                'per_order_limit' => 10,
                'refundable' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['price_xof']);
    }

    /**
     * Test can archive ticket type.
     */
    public function test_can_archive_ticket_type(): void
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

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/events/{$event->id}/ticket-types/{$ticketType->id}");

        $response->assertStatus(200);

        // Verify ticket type is archived (soft delete)
        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticketType->id,
        ]);

        $this->assertNotNull($ticketType->fresh()->archived_at);
    }

    /**
     * Test ticket type availability status.
     */
    public function test_ticket_type_availability_status(): void
    {
        $event = Event::factory()->create();

        // Test available ticket type
        $availableTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'archived_at' => null,
            'sales_start' => null,
            'sales_end' => null,
        ]);

        $this->assertTrue($availableTicket->isAvailable());
        $this->assertEquals('Available', $availableTicket->availability_status);

        // Test archived ticket type
        $archivedTicket = TicketType::factory()->archived()->create([
            'event_id' => $event->id,
        ]);

        $this->assertFalse($archivedTicket->isAvailable());
        $this->assertEquals('Archived', $archivedTicket->availability_status);

        // Test ticket type with future sales start
        $futureTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'sales_start' => now()->addDays(7),
        ]);

        $this->assertFalse($futureTicket->isAvailable());
        $this->assertEquals('Sales Not Started', $futureTicket->availability_status);

        // Test ticket type with past sales end
        $endedTicket = TicketType::factory()->create([
            'event_id' => $event->id,
            'sales_end' => now()->subDay(),
        ]);

        $this->assertFalse($endedTicket->isAvailable());
        $this->assertEquals('Sales Ended', $endedTicket->availability_status);
    }
}
