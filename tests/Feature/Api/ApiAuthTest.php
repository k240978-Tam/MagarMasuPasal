<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Notification\Notifications\FailedLoginAlert;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\UserManagement\Services\TwoFactorAuthService;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_login_issues_a_bearer_token(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id, 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'test-suite',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.token_type', 'Bearer');
        $response->assertJsonPath('data.user.business_id', $branch->business_id);
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_an_invalid_password_is_rejected_and_notifies_management(): void
    {
        NotificationFacade::fake();

        $branch = BranchFactory::new()->create();
        $owner = User::factory()->create(['business_id' => $branch->business_id]);
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'business_id' => null]);
        $owner->assignRole($role);

        $user = User::factory()->create(['business_id' => $branch->business_id, 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'wrong', 'device_name' => 'test-suite',
        ]);

        $response->assertUnauthorized();
        NotificationFacade::assertSentTo($owner, FailedLoginAlert::class);
    }

    public function test_a_two_factor_account_requires_the_code_and_rejects_a_wrong_one(): void
    {
        $branch = BranchFactory::new()->create();
        $twoFactor = app(TwoFactorAuthService::class);
        $secret = $twoFactor->generateSecretKey();

        $user = User::factory()->create([
            'business_id' => $branch->business_id, 'password' => 'password',
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode([]),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'test-suite',
        ])->assertStatus(428);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'test-suite', 'two_factor_code' => '000000',
        ])->assertUnauthorized();

        $goodCode = (new Google2FA)->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'test-suite', 'two_factor_code' => $goodCode,
        ])->assertCreated();
    }

    public function test_me_returns_the_authenticated_user_and_logout_revokes_the_token(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id, 'password' => 'password']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'test-suite',
        ])->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Laravel's Sanctum RequestGuard caches its resolved user on the
        // guard instance itself, and that instance persists across
        // sequential calls within one test method — forgetGuards() clears
        // it so this assertion reflects a real re-authentication, the way
        // a genuinely separate HTTP request would (each gets a fresh
        // container, so this caching never happens outside of tests).
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    /**
     * The middleware-ordering fix from Phase 6 (ResolveTenant before
     * SubstituteBindings) only matters for session-authenticated web
     * requests unless it also applies correctly to Sanctum-token requests —
     * this proves TenantContext resolves the token owner's business over
     * the API too, not just for the session guard. The Products endpoint
     * (added alongside this one) has its own, fuller cross-tenant check.
     */
    public function test_api_token_resolves_the_correct_tenant_context(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        $userA = User::factory()->create(['business_id' => $branchA->business_id, 'password' => 'password']);
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $userA->email, 'password' => 'password', 'device_name' => 'test-suite',
        ])->json('data.token');

        $visible = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')->json();

        $this->assertSame($branchA->business_id, $visible['data']['business_id']);
        $this->assertNotSame($branchB->business_id, $visible['data']['business_id']);
    }
}
