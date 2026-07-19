<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the throttle limiters actually reject the 6th request, not just
 * that they're registered — a brute-force login script or a runaway POS
 * bug must hit a 429, not silently keep retrying forever.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sixth_login_attempt_in_a_minute_is_throttled(): void
    {
        User::factory()->create(['email' => 'owner@example.test', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', ['email' => 'owner@example.test', 'password' => 'wrong-password']);
            $response->assertStatus(302);
            $response->assertSessionHasErrors('email');
        }

        $response = $this->post('/login', ['email' => 'owner@example.test', 'password' => 'wrong-password']);
        $response->assertStatus(429);
    }

    public function test_login_throttling_is_keyed_per_email_not_shared_across_accounts(): void
    {
        User::factory()->create(['email' => 'owner@example.test', 'password' => 'password']);
        User::factory()->create(['email' => 'other@example.test', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'owner@example.test', 'password' => 'wrong-password']);
        }

        // Same IP, different account — must not be blocked by owner@example.test's lockout.
        $response = $this->post('/login', ['email' => 'other@example.test', 'password' => 'password']);
        $response->assertRedirect('/dashboard');
    }
}
