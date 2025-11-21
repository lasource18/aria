<?php

namespace Tests\Feature;

use App\Models\Org;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $org = Org::factory()->create();

        $response = $this->get("/org/{$org->id}/dashboard");

        // Should redirect to login or return 401/403
        $this->assertTrue(
            $response->status() === 401 || $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_dashboard_requires_org_membership(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();

        // User is not a member of the org
        $response = $this->actingAs($user)->get("/org/{$org->id}/dashboard");

        // Should be forbidden
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_org_member_can_access_dashboard(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();
        $org->members()->create(['user_id' => $user->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->get("/org/{$org->id}/dashboard");

        $response->assertStatus(200);
    }

    public function test_events_list_requires_authentication(): void
    {
        $org = Org::factory()->create();

        $response = $this->get("/org/{$org->id}/events");

        $this->assertTrue(
            $response->status() === 401 || $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_events_list_requires_org_membership(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/org/{$org->id}/events");

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_org_member_can_access_events_list(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();
        $org->members()->create(['user_id' => $user->id, 'role' => 'admin']);

        $response = $this->actingAs($user)->get("/org/{$org->id}/events");

        $response->assertStatus(200);
    }

    public function test_events_create_requires_authentication(): void
    {
        $org = Org::factory()->create();

        $response = $this->get("/org/{$org->id}/events/create");

        $this->assertTrue(
            $response->status() === 401 || $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_events_create_requires_org_membership(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/org/{$org->id}/events/create");

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_org_member_can_access_events_create(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();
        $org->members()->create(['user_id' => $user->id, 'role' => 'staff']);

        $response = $this->actingAs($user)->get("/org/{$org->id}/events/create");

        $response->assertStatus(200);
    }

    public function test_events_edit_requires_authentication(): void
    {
        $org = Org::factory()->create();

        $response = $this->get("/org/{$org->id}/events/test-event-id/edit");

        $this->assertTrue(
            $response->status() === 401 || $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_events_edit_requires_org_membership(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get("/org/{$org->id}/events/test-event-id/edit");

        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302
        );
    }

    public function test_org_member_can_access_events_edit(): void
    {
        $org = Org::factory()->create();
        $user = User::factory()->create();
        $org->members()->create(['user_id' => $user->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->get("/org/{$org->id}/events/test-event-id/edit");

        $response->assertStatus(200);
    }
}
