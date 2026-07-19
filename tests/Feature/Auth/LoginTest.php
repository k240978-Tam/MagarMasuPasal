<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_staff_member_can_sign_in_and_reach_the_dashboard(): void
    {
        $branch = BranchFactory::new()->create();

        $user = User::factory()->create([
            'business_id' => $branch->business_id,
            'default_branch_id' => $branch->id,
            'email' => 'owner@example.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($branch->business->name);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
