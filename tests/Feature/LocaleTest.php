<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Tests\TestCase;

/**
 * The interface language is a per-user preference, so a Nepali-speaking
 * cashier and an English-reading owner can share one shop.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_interface_renders_in_the_users_language(): void
    {
        $user = $this->user('ne');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('ड्यासबोर्ड', escape: false)
            ->assertDontSee('>Dashboard<', escape: false);
    }

    public function test_switching_language_persists_on_the_user(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/locale', ['locale' => 'ne'])
            ->assertRedirect('/dashboard');

        $this->assertSame('ne', $user->fresh()->locale);
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($user->fresh()->locale);
    }

    public function test_an_unknown_stored_language_falls_back_to_the_default(): void
    {
        // A locale removed from config after a user picked it must not leave
        // them staring at translation keys.
        $user = $this->user();
        $user->forceFill(['locale' => 'de'])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    private function user(?string $locale = null): User
    {
        $branch = BranchFactory::new()->create();

        return User::factory()->create([
            'business_id' => $branch->business_id,
            'default_branch_id' => $branch->id,
            'locale' => $locale,
        ]);
    }
}
