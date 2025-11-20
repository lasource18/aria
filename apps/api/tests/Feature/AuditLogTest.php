<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Org;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_registration_creates_audit_log(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'test@example.com',
            'phone' => '+2250701234567',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'full_name' => 'Test User',
        ]);

        $response->assertStatus(201);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.registered',
            'entity_type' => 'User',
        ]);

        $auditLog = AuditLog::where('action', 'user.registered')->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('test@example.com', $auditLog->metadata['email']);
        $this->assertNotNull($auditLog->ip_address);
    }

    public function test_user_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.logged_in',
            'entity_type' => 'User',
            'user_id' => $user->id,
        ]);

        $auditLog = AuditLog::where('action', 'user.logged_in')->first();
        $this->assertEquals('email', $auditLog->metadata['login_method']);
    }

    public function test_failed_login_creates_audit_log(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.login_failed',
            'entity_type' => 'User',
        ]);
    }

    public function test_user_logout_creates_audit_log(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.logged_out',
            'entity_type' => 'User',
            'user_id' => $user->id,
        ]);
    }

    public function test_org_creation_creates_audit_log(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orgs', [
                'name' => 'Test Organization',
                'country_code' => 'CI',
            ]);

        $response->assertStatus(201);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org.created',
            'entity_type' => 'Org',
        ]);

        $auditLog = AuditLog::where('action', 'org.created')->first();
        $this->assertEquals('Test Organization', $auditLog->metadata['name']);
        $this->assertEquals('CI', $auditLog->metadata['country_code']);
    }

    public function test_org_update_creates_audit_log(): void
    {
        $user = User::factory()->create();
        $org = Org::factory()->create();
        $org->addMember($user, 'owner');
        $token = $user->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/orgs/{$org->id}", [
                'name' => 'Updated Organization Name',
            ]);

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org.updated',
            'entity_type' => 'Org',
            'entity_id' => $org->id,
        ]);

        $auditLog = AuditLog::where('action', 'org.updated')->first();
        $this->assertArrayHasKey('name', $auditLog->changes);
        $this->assertEquals('Updated Organization Name', $auditLog->changes['name']['to']);
    }

    public function test_member_addition_creates_audit_log(): void
    {
        $owner = User::factory()->create();
        $newMember = User::factory()->create();
        $org = Org::factory()->create();
        $org->addMember($owner, 'owner');
        $token = $owner->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/orgs/{$org->id}/members", [
                'user_id' => $newMember->id,
                'role' => 'admin',
            ]);

        $response->assertStatus(201);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org.member_added',
            'entity_type' => 'OrgMember',
        ]);

        $auditLog = AuditLog::where('action', 'org.member_added')->first();
        $this->assertEquals($newMember->id, $auditLog->metadata['added_user_id']);
        $this->assertEquals('admin', $auditLog->metadata['role']);
    }

    public function test_member_role_update_creates_audit_log(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::factory()->create();
        $org->addMember($owner, 'owner');
        $org->addMember($member, 'staff');
        $token = $owner->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/orgs/{$org->id}/members/{$member->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org.member_role_updated',
            'entity_type' => 'OrgMember',
        ]);

        $auditLog = AuditLog::where('action', 'org.member_role_updated')->first();
        $this->assertEquals('staff', $auditLog->changes['role']['from']);
        $this->assertEquals('admin', $auditLog->changes['role']['to']);
    }

    public function test_member_removal_creates_audit_log(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Org::factory()->create();
        $org->addMember($owner, 'owner');
        $org->addMember($member, 'staff');
        $token = $owner->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/orgs/{$org->id}/members/{$member->id}");

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'org.member_removed',
            'entity_type' => 'OrgMember',
        ]);

        $auditLog = AuditLog::where('action', 'org.member_removed')->first();
        $this->assertEquals($member->id, $auditLog->metadata['removed_user_id']);
        $this->assertEquals('staff', $auditLog->metadata['previous_role']);
    }

    public function test_password_reset_request_creates_audit_log(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.password_reset_requested',
            'entity_type' => 'User',
            'user_id' => $user->id,
        ]);
    }

    public function test_audit_log_captures_ip_and_user_agent(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('access-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('User-Agent', 'TestBrowser/1.0')
            ->postJson('/api/v1/orgs', [
                'name' => 'Test Org',
            ]);

        $response->assertStatus(201);

        $auditLog = AuditLog::where('action', 'org.created')->first();
        $this->assertNotNull($auditLog->ip_address);
        $this->assertEquals('TestBrowser/1.0', $auditLog->user_agent);
    }
}
