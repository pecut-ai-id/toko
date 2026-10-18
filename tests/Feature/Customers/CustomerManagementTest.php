<?php

namespace Tests\Feature\Customers;

use App\Enums\BusinessRole;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use CreatesBusinessUsers, RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Toko Sejahtera',
            'phone' => '081298765432',
            'email' => 'sejahtera@example.test',
            'address' => 'Jl. Pasar Baru No. 7',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_guest_cannot_access_customers(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_customers_are_scoped_to_the_active_business(): void
    {
        [$user, $business] = $this->businessWithUser();

        Customer::factory()->forBusiness($business)->create(['name' => 'Pelanggan Kita']);
        Customer::factory()->create(['name' => 'Pelanggan Bisnis Lain']);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Pelanggan Kita')
            ->assertDontSee('Pelanggan Bisnis Lain');
    }

    public function test_owner_can_create_a_customer(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('customers.store'), $this->validPayload())
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'business_id' => $business->getKey(),
            'name' => 'Toko Sejahtera',
        ]);
    }

    public function test_customer_requires_a_name_and_a_valid_email(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('customers.store'), [
                'name' => '',
                'email' => 'bukan-email',
            ])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_owner_can_update_a_customer(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create(['name' => 'Nama Lama']);

        $this->actingAs($user)
            ->put(route('customers.update', $customer), $this->validPayload(['name' => 'Nama Baru']))
            ->assertRedirect(route('customers.index'));

        $this->assertSame('Nama Baru', $customer->fresh()->name);
    }

    public function test_user_cannot_touch_a_foreign_customer(): void
    {
        [$user] = $this->businessWithUser();
        $foreign = Customer::factory()->create();

        $this->actingAs($user)->get(route('customers.edit', $foreign))->assertForbidden();
        $this->actingAs($user)->put(route('customers.update', $foreign), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->delete(route('customers.destroy', $foreign))->assertForbidden();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_staff_cannot_manage_customers(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);
        $customer = Customer::factory()->forBusiness($business)->create();

        $this->actingAs($staff)->get(route('customers.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('customers.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($staff)->put(route('customers.update', $customer), $this->validPayload())->assertForbidden();
    }

    public function test_deleting_a_customer_deactivates_it(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['id' => $customer->getKey()]);
        $this->assertFalse($customer->fresh()->is_active);
    }
}
