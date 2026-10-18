<?php

namespace Tests\Feature\Products;

use App\Enums\BusinessRole;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesBusinessUsers, RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'KP-001',
            'name' => 'Kopi Arabika 1kg',
            'unit' => 'kg',
            'sell_price' => '85000',
            'cost_price' => '60000',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_guest_cannot_access_products(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
    }

    public function test_products_are_scoped_to_the_active_business(): void
    {
        [$user, $business] = $this->businessWithUser();

        Product::factory()->forBusiness($business)->create(['name' => 'Kopi Arabika']);
        Product::factory()->create(['name' => 'Produk Bisnis Lain']);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Kopi Arabika')
            ->assertDontSee('Produk Bisnis Lain');
    }

    public function test_owner_can_create_a_product(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('products.store'), $this->validPayload())
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'business_id' => $business->getKey(),
            'sku' => 'KP-001',
            'name' => 'Kopi Arabika 1kg',
        ]);
    }

    public function test_business_id_cannot_be_injected_from_the_form(): void
    {
        [$user, $business] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('products.store'), $this->validPayload([
                'business_id' => $otherBusiness->getKey(),
            ]))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'KP-001',
            'business_id' => $business->getKey(),
        ]);
    }

    public function test_product_requires_valid_data(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('products.store'), [
                'sku' => '',
                'name' => '',
                'unit' => '',
                'sell_price' => '-1',
                'cost_price' => 'bukan-angka',
            ])
            ->assertSessionHasErrors(['sku', 'name', 'unit', 'sell_price', 'cost_price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_sku_must_be_unique_within_the_business(): void
    {
        [$user, $business] = $this->businessWithUser();

        Product::factory()->forBusiness($business)->create(['sku' => 'KP-001']);

        $this->actingAs($user)
            ->post(route('products.store'), $this->validPayload(['sku' => 'KP-001']))
            ->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_the_same_sku_is_allowed_in_a_different_business(): void
    {
        [$user, $business] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        Product::factory()->forBusiness($otherBusiness)->create(['sku' => 'KP-001']);

        $this->actingAs($user)
            ->post(route('products.store'), $this->validPayload(['sku' => 'KP-001']))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'KP-001',
            'business_id' => $business->getKey(),
        ]);
    }

    public function test_owner_can_update_a_product(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['name' => 'Nama Lama']);

        $this->actingAs($user)
            ->put(route('products.update', $product), $this->validPayload([
                'sku' => $product->sku,
                'name' => 'Nama Baru',
                'sell_price' => '99000',
            ]))
            ->assertRedirect(route('products.index'));

        $fresh = $product->fresh();
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('99000.00', (string) $fresh->sell_price);
    }

    public function test_user_cannot_view_or_update_a_foreign_product(): void
    {
        [$user] = $this->businessWithUser();
        $foreign = Product::factory()->create();

        $this->actingAs($user)
            ->get(route('products.edit', $foreign))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('products.update', $foreign), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('products.destroy', $foreign))
            ->assertForbidden();

        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_staff_cannot_manage_products(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($staff)
            ->get(route('products.create'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('products.store'), $this->validPayload())
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('products.update', $product), $this->validPayload(['sku' => $product->sku]))
            ->assertForbidden();
    }

    public function test_staff_can_view_products(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);

        Product::factory()->forBusiness($business)->create(['name' => 'Kopi Arabika']);

        $this->actingAs($staff)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Kopi Arabika');
    }

    public function test_deleting_a_product_deactivates_it_instead_of_removing_the_row(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);
        $this->assertFalse($product->fresh()->is_active);
    }
}
