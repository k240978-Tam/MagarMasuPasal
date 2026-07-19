<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\UserManagement\Services\TwoFactorAuthService;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Proves the whole 2FA lifecycle end to end: enabling requires a real TOTP
 * code (not just "any secret exists"), a confirmed account can never reach
 * an authenticated session on password alone, a correct code completes
 * login, and a recovery code works exactly once.
 */
class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function currentOtp(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    public function test_a_user_can_enable_two_factor_with_a_valid_code(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id, 'password' => 'password']);

        $this->actingAs($user)->post('/account/two-factor/enable')->assertRedirect(route('two-factor.show'));

        $secret = $this->app['session']->get('two_factor.pending_secret');
        $this->assertNotEmpty($secret);

        $badCode = $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => '000000']);
        $badCode->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $goodCode = $this->currentOtp($secret);
        $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => $goodCode])->assertRedirect(route('two-factor.show'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotEmpty(json_decode($user->two_factor_recovery_codes, true));
    }

    public function test_logging_in_with_two_factor_enabled_requires_the_challenge_before_reaching_the_dashboard(): void
    {
        $branch = BranchFactory::new()->create();
        $twoFactor = app(TwoFactorAuthService::class);
        $secret = $twoFactor->generateSecretKey();

        $user = User::factory()->create([
            'business_id' => $branch->business_id,
            'email' => 'owner@example.test',
            'password' => 'password',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => json_encode($twoFactor->hashRecoveryCodes(['AAAA-BBBB'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $login = $this->post('/login', ['email' => 'owner@example.test', 'password' => 'password']);
        $login->assertRedirect(route('two-factor.challenge'));

        // The password check alone must not grant an authenticated session.
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');

        $goodCode = $this->currentOtp($secret);
        $this->assertTrue($twoFactor->verifyCode($secret, $goodCode));

        $verify = $this->post('/two-factor-challenge', ['code' => $goodCode]);
        $verify->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_works_once_and_then_is_rejected(): void
    {
        $branch = BranchFactory::new()->create();
        $twoFactor = app(TwoFactorAuthService::class);
        $secret = $twoFactor->generateSecretKey();

        $user = User::factory()->create([
            'business_id' => $branch->business_id,
            'email' => 'owner@example.test',
            'password' => 'password',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => json_encode($twoFactor->hashRecoveryCodes(['RECOVERY-1'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => 'RECOVERY-1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Log back out and attempt to reuse the same recovery code.
        $this->post('/logout');
        $this->post('/login', ['email' => 'owner@example.test', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => 'RECOVERY-1'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_disabling_two_factor_requires_the_current_password(): void
    {
        $branch = BranchFactory::new()->create();
        $twoFactor = app(TwoFactorAuthService::class);
        $secret = $twoFactor->generateSecretKey();

        $user = User::factory()->create([
            'business_id' => $branch->business_id,
            'password' => 'password',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => json_encode($twoFactor->hashRecoveryCodes(['X-1'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)->delete('/account/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->actingAs($user)->delete('/account/two-factor', ['password' => 'password'])->assertRedirect(route('two-factor.show'));
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
