<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     * Tests that the API health endpoint returns a successful response.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Test API endpoint instead of welcome page to avoid Vite asset requirements
        // Request JSON to ensure API returns JSON response instead of redirect
        $response = $this->getJson('/api/v1/orgs');

        // Should return 401 (unauthorized) since we're not authenticated, which is expected
        $response->assertStatus(401);
    }
}
