<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, RefreshDatabase;

    public function test_guest_cannot_view_a_product_detail_page(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.show', $product))->assertRedirect(route('login'));
    }

    public function test_product_detail_shows_stock_and_the_movement_ledger(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create([
            'name' => 'Kopi Arabika',
            'sell_price' => 85000,
        ]);

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '60.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase));

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Kopi Arabika')
            ->assertSee('Rp85.000')
            ->assertSee($purchase->number)
            ->assertSee('Penerimaan pembelian '.$purchase->number)
            ->assertSee($user->name);
    }

    public function test_product_detail_shows_an_empty_state_without_movements(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Belum ada pergerakan stok untuk produk ini.');
    }

    public function test_a_user_cannot_view_a_foreign_product_detail_page(): void
    {
        [$user] = $this->businessWithUser();
        $foreign = Product::factory()->create();

        $this->actingAs($user)
            ->get(route('products.show', $foreign))
            ->assertForbidden();
    }

    public function test_money_fields_accept_indonesian_formatting(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('products.store'), [
                'sku' => 'ID-001',
                'name' => 'Produk Format Indonesia',
                'unit' => 'kg',
                'sell_price' => '1.275.000,50',
                'cost_price' => '85.000',
                'is_active' => '1',
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('sku', 'ID-001')->firstOrFail();

        $this->assertSame('1275000.50', (string) $product->sell_price);
        $this->assertSame('85000.00', (string) $product->cost_price);
    }

    public function test_money_fields_reject_text(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('products.store'), [
                'sku' => 'ID-002',
                'name' => 'Produk Salah',
                'unit' => 'kg',
                'sell_price' => 'seribu',
                'cost_price' => '85.000',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('sell_price');

        $this->assertDatabaseCount('products', 0);
    }
}
