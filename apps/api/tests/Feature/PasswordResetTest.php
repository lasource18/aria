<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_with_email(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'message' => 'If the email or phone exists in our system, you will receive password reset instructions.',
            ],
        ]);

        // Verify token was created
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_request_password_reset_with_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'phone' => '+2250701234567',
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => '+2250701234567',
        ]);

        $response->assertStatus(200);

        // Verify token was created for the user's email
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_reset_request_with_nonexistent_email_returns_200(): void
    {
        // Should return 200 to prevent user enumeration
        $response = $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);

        // No token should be created
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'nonexistent@example.com',
        ]);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'message' => 'Password reset successful. Please log in with your new password.',
            ],
        ]);

        // Verify password was updated
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));

        // Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_reset_fails_with_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        // Create token that expired over 1 hour ago
        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'INVALID_TOKEN',
                'message' => 'Reset token is invalid or expired',
            ],
        ]);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'invalid-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'INVALID_TOKEN',
            ],
        ]);
    }

    public function test_password_reset_validates_password_requirements(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        // Test with password that doesn't meet requirements
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        // Should fail validation
        $response->assertStatus(422);
    }

    public function test_password_reset_requires_password_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        // Test without password confirmation
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'ValidPassword123',
        ]);

        // Should fail validation
        $response->assertStatus(422);
    }

    public function test_password_reset_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Create some tokens
        $user->createToken('access-token-1');
        $user->createToken('access-token-2');
        $this->assertEquals(2, $user->tokens()->count());

        $token = Str::random(64);
        $hashedToken = Hash::make($token);

        DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => $hashedToken,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(200);

        // Verify all tokens were revoked
        $this->assertEquals(0, $user->fresh()->tokens()->count());
    }

    public function test_new_password_reset_request_invalidates_previous_tokens(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // First request
        $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => 'test@example.com',
        ]);

        // Second request
        $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => 'test@example.com',
        ]);

        // Only one token should exist
        $tokens = DB::table('password_reset_tokens')
            ->where('email', 'test@example.com')
            ->get();

        $this->assertCount(1, $tokens);
    }

    public function test_debug_token_returned_in_testing_environment(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/v1/auth/password/reset-request', [
            'email_or_phone' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        // In testing environment, debug_token should be present for test assertions
        $response->assertJsonStructure([
            'data' => [
                'message',
                'debug_token',
            ],
        ]);

        // Verify we can use the returned token to reset password
        $token = $response->json('data.debug_token');
        $this->assertNotNull($token);

        $resetResponse = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $resetResponse->assertStatus(200);
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));
    }
}
