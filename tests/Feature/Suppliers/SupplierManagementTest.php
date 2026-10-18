<?php

namespace Tests\Feature\Suppliers;

use App\Enums\BusinessRole;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use CreatesBusinessUsers, RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'PT Sumber Makmur',
            'phone' => '081234567890',
            'email' => 'sales@sumbermakmur.test',
            'address' => 'Jl. Merdeka No. 1',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_guest_cannot_access_suppliers(): void
    {
        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
    }

    public function test_suppliers_are_scoped_to_the_active_business(): void
    {
        [$user, $business] = $this->businessWithUser();

        Supplier::factory()->forBusiness($business)->create(['name' => 'Supplier Kita']);
        Supplier::factory()->create(['name' => 'Supplier Bisnis Lain']);

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Supplier Kita')
            ->assertDontSee('Supplier Bisnis Lain');
    }

    public function test_owner_can_create_a_supplier_with_optional_contacts(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('suppliers.store'), $this->validPayload())
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'business_id' => $business->getKey(),
            'name' => 'PT Sumber Makmur',
            'phone' => '081234567890',
        ]);
    }

    public function test_optional_contact_fields_may_be_empty(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('suppliers.store'), [
                'name' => 'CV Tanpa Kontak',
                'phone' => '',
                'email' => '',
                'address' => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'business_id' => $business->getKey(),
            'name' => 'CV Tanpa Kontak',
        ]);
    }

    public function test_supplier_requires_a_name_and_a_valid_email(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('suppliers.store'), [
                'name' => '',
                'email' => 'bukan-email',
            ])
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseCount('suppliers', 0);
    }

    public function test_owner_can_update_a_supplier(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create(['name' => 'Nama Lama']);

        $this->actingAs($user)
            ->put(route('suppliers.update', $supplier), $this->validPayload(['name' => 'Nama Baru']))
            ->assertRedirect(route('suppliers.index'));

        $this->assertSame('Nama Baru', $supplier->fresh()->name);
    }

    public function test_user_cannot_touch_a_foreign_supplier(): void
    {
        [$user] = $this->businessWithUser();
        $foreign = Supplier::factory()->create();

        $this->actingAs($user)->get(route('suppliers.edit', $foreign))->assertForbidden();
        $this->actingAs($user)->put(route('suppliers.update', $foreign), $this->validPayload())->assertForbidden();
        $this->actingAs($user)->delete(route('suppliers.destroy', $foreign))->assertForbidden();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_staff_cannot_manage_suppliers(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);
        $supplier = Supplier::factory()->forBusiness($business)->create();

        $this->actingAs($staff)->get(route('suppliers.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('suppliers.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($staff)->put(route('suppliers.update', $supplier), $this->validPayload())->assertForbidden();
    }

    public function test_deleting_a_supplier_deactivates_it(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->getKey()]);
        $this->assertFalse($supplier->fresh()->is_active);
    }
}
