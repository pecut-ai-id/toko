<?php

namespace Tests\Feature\Auth;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_switch_business(): void
    {
        $business = Business::factory()->create();

        $this->post(route('businesses.switch', $business))
            ->assertRedirect(route('login'));
    }

    public function test_user_with_active_membership_can_access_dashboard(): void
    {
        $business = Business::factory()->create(['name' => 'Toko Sumber Rejeki']);
        $user = User::factory()->create();

        $this->attach($business, $user, BusinessRole::Owner);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Toko Sumber Rejeki');
    }

    public function test_user_without_any_membership_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_user_with_only_inactive_membership_is_forbidden(): void
    {
        $business = Business::factory()->create();
        $user = User::factory()->create();

        $this->attach($business, $user, BusinessRole::Staff, active: false);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_user_can_switch_to_a_business_they_belong_to(): void
    {
        $first = Business::factory()->create();
        $second = Business::factory()->create();
        $user = User::factory()->create();

        $this->attach($first, $user, BusinessRole::Owner);
        $this->attach($second, $user, BusinessRole::Staff);

        $this->actingAs($user)
            ->post(route('businesses.switch', $second))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('current_business_id', $second->getKey());
    }

    public function test_user_cannot_switch_to_a_business_they_do_not_belong_to(): void
    {
        $owned = Business::factory()->create();
        $foreign = Business::factory()->create(['name' => 'Bisnis Orang Lain']);
        $user = User::factory()->create();

        $this->attach($owned, $user, BusinessRole::Owner);

        $this->actingAs($user)
            ->post(route('businesses.switch', $foreign))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Bisnis Orang Lain');
    }

    public function test_user_cannot_switch_to_an_inactive_business(): void
    {
        $business = Business::factory()->inactive()->create();
        $user = User::factory()->create();

        $this->attach($business, $user, BusinessRole::Owner);

        $this->actingAs($user)
            ->post(route('businesses.switch', $business))
            ->assertForbidden();
    }

    public function test_inactive_membership_does_not_grant_switch_access(): void
    {
        $active = Business::factory()->create();
        $disabled = Business::factory()->create();
        $user = User::factory()->create();

        $this->attach($active, $user, BusinessRole::Owner);
        $this->attach($disabled, $user, BusinessRole::Staff, active: false);

        $this->actingAs($user)
            ->post(route('businesses.switch', $disabled))
            ->assertForbidden();
    }

    public function test_stale_session_business_falls_back_to_first_active_membership(): void
    {
        $removed = Business::factory()->create();
        $remaining = Business::factory()->create(['name' => 'Bisnis Tersisa']);
        $user = User::factory()->create();

        $this->attach($remaining, $user, BusinessRole::Owner);

        // Session menyimpan bisnis yang bukan lagi keanggotaan pengguna.
        $this->actingAs($user)
            ->withSession(['current_business_id' => $removed->getKey()])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bisnis Tersisa');
    }

    private function attach(Business $business, User $user, BusinessRole $role, bool $active = true): void
    {
        $business->users()->attach($user->getKey(), [
            'role' => $role->value,
            'is_active' => $active,
        ]);
    }
}
