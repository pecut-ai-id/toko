<?php

namespace Tests\Feature\Purchases;

use App\Enums\BusinessRole;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\TestCase;

class PurchaseReceivingTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, RefreshDatabase;

    public function test_receiving_increases_stock_and_records_a_movement_per_item(): void
    {
        [$user, $business] = $this->businessWithUser();
        $productA = Product::factory()->forBusiness($business)->create();
        $productB = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $productA, 'quantity' => 10, 'unit_cost' => '50.000'],
            ['product' => $productB, 'quantity' => 2, 'unit_cost' => '25.000'],
        ]);

        $this->actingAs($user)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('10.00', (string) $productA->fresh()->stock);
        $this->assertSame('2.00', (string) $productB->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 2);

        $movement = InventoryMovement::query()->where('product_id', $productA->getKey())->firstOrFail();

        $this->assertSame('10.00', (string) $movement->quantity);
        $this->assertSame(Purchase::class, $movement->source_type);
        $this->assertTrue($movement->source->is($purchase));
        $this->assertSame($user->getKey(), $movement->user_id);
        $this->assertSame('Penerimaan pembelian '.$purchase->number, $movement->reason);

        $fresh = $purchase->fresh();
        $this->assertTrue($fresh->status->isReceived());
        $this->assertNotNull($fresh->received_at);
        $this->assertSame($user->getKey(), $fresh->received_by);
    }

    public function test_a_received_purchase_cannot_be_received_twice(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->assertSame('10.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_staff_can_confirm_receiving(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($staff, $business, [
            ['product' => $product, 'quantity' => 5, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($staff)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('5.00', (string) $product->fresh()->stock);
    }

    public function test_a_user_cannot_receive_a_foreign_purchase(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 5, 'unit_cost' => '10.000'],
        ]);

        [$outsider] = $this->businessWithUser();

        $this->actingAs($outsider)
            ->post(route('purchases.receive', $purchase))
            ->assertForbidden();

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertTrue($purchase->fresh()->status->isDraft());
    }

    /**
     * D-027: harga pokok default produk mengikuti harga beli terakhir yang
     * benar-benar diterima.
     */
    public function test_receiving_updates_the_product_default_cost_price(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['cost_price' => 40000]);

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '55.000'],
        ]);

        $this->assertSame('40000.00', (string) $product->fresh()->cost_price, 'Draf belum mengubah harga pokok.');

        $this->actingAs($user)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $fresh = $product->fresh();

        $this->assertSame('55000.00', (string) $fresh->cost_price);
        $this->assertSame('10.00', (string) $fresh->stock, 'Pembaruan harga pokok tidak mengganggu stok.');
    }

    public function test_the_latest_received_purchase_price_wins(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['cost_price' => 40000]);

        $first = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $first));

        $this->assertSame('50000.00', (string) $product->fresh()->cost_price);

        $second = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 4, 'unit_cost' => '62.500'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $second));

        $this->assertSame('62500.00', (string) $product->fresh()->cost_price);
    }

    public function test_cancelling_the_last_received_purchase_restores_the_previous_cost_price(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['cost_price' => 40000]);

        $first = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $first));

        $second = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 4, 'unit_cost' => '62.500'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $second));

        $this->assertSame('62500.00', (string) $product->fresh()->cost_price);

        $this->actingAs($user)->post(route('purchases.cancel', $second));

        $this->assertSame(
            '50000.00',
            (string) $product->fresh()->cost_price,
            'Harga pokok kembali ke pembelian terakhir yang masih berlaku.'
        );
    }

    public function test_cancelling_a_received_purchase_keeps_the_last_known_price_when_none_remains(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['cost_price' => 40000]);

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '55.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase));
        $this->actingAs($user)->post(route('purchases.cancel', $purchase));

        $this->assertSame(
            '55000.00',
            (string) $product->fresh()->cost_price,
            'Tidak ada pembelian berlaku lain, jadi angka terakhir dibiarkan daripada menebak.'
        );
    }
}
