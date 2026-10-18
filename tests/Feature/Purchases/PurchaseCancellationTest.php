<?php

namespace Tests\Feature\Purchases;

use App\Enums\BusinessRole;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\TestCase;

class PurchaseCancellationTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, RefreshDatabase;

    public function test_cancelling_a_draft_does_not_touch_stock(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)
            ->post(route('purchases.cancel', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertTrue($purchase->fresh()->status->isCancelled());
        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_cancelling_a_received_purchase_creates_a_traced_reversal(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase));

        $this->assertSame('10.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('purchases.cancel', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('0.00', (string) $product->fresh()->stock);

        $fresh = $purchase->fresh();
        $this->assertTrue($fresh->status->isCancelled());
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame($user->getKey(), $fresh->cancelled_by);

        // Dua baris: penerimaan (+10) dan reversal (-10).
        $this->assertDatabaseCount('inventory_movements', 2);

        $reversal = InventoryMovement::query()
            ->where('product_id', $product->getKey())
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertSame('-10.00', (string) $reversal->quantity);
        $this->assertSame('0.00', (string) $reversal->balance_after);
        $this->assertSame('Pembatalan pembelian '.$purchase->number, $reversal->reason);
        $this->assertTrue($reversal->source->is($purchase));

        // Buku besar tetap konsisten dengan stok produk.
        $this->assertSame(
            (string) $product->fresh()->stock,
            (string) number_format((float) $product->movements()->sum('quantity'), 2, '.', ''),
        );
    }

    public function test_cancellation_is_blocked_when_stock_has_already_been_consumed(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase));

        DB::transaction(fn () => app(InventoryService::class)->record($product, '-10', 'Terjual'));

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.cancel', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertTrue($purchase->fresh()->status->isReceived());
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_a_cancelled_purchase_cannot_be_received_or_cancelled_again(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.cancel', $purchase));

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.receive', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.cancel', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_staff_cannot_cancel_purchases(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($owner, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($owner)->post(route('purchases.receive', $purchase));

        $this->actingAs($staff)
            ->post(route('purchases.cancel', $purchase))
            ->assertForbidden();

        $this->assertTrue($purchase->fresh()->status->isReceived());
        $this->assertSame('10.00', (string) $product->fresh()->stock);
    }

    public function test_a_failed_reversal_rolls_back_every_change_in_the_transaction(): void
    {
        [$user, $business] = $this->businessWithUser();
        $productA = Product::factory()->forBusiness($business)->create();
        $productB = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $productA, 'quantity' => 10, 'unit_cost' => '10.000'],
            ['product' => $productB, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase));

        // Simulasikan stok produk B sudah habis terpakai transaksi lain.
        DB::transaction(fn () => app(InventoryService::class)->record($productB, '-10', 'Pemakaian lain'));

        $this->assertSame('10.00', (string) $productA->fresh()->stock);
        $this->assertSame('0.00', (string) $productB->fresh()->stock);

        // Pembatalan gagal di item B; item A yang sempat diproses harus dibatalkan.
        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.cancel', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->assertSame('10.00', (string) $productA->fresh()->stock, 'Stok produk A harus kembali seperti semula.');
        $this->assertSame('0.00', (string) $productB->fresh()->stock);
        $this->assertTrue($purchase->fresh()->status->isReceived());
        $this->assertDatabaseCount('inventory_movements', 3);
    }

    public function test_a_user_cannot_cancel_a_foreign_purchase(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        [$outsider] = $this->businessWithUser();

        $this->actingAs($outsider)
            ->post(route('purchases.cancel', $purchase))
            ->assertForbidden();

        $this->assertTrue($purchase->fresh()->status->isDraft());
    }

    /**
     * Utang yang sudah dibayar tidak bisa dibatalkan begitu saja
     * (konsisten dengan penjualan): pembayarannya di-void lebih dulu.
     */
    public function test_a_received_purchase_with_an_active_payment_cannot_be_cancelled(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase));

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '100.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.cancel', $purchase))
            ->assertSessionHasErrors('purchase');

        $this->assertTrue($purchase->fresh()->status->isReceived());
        $this->assertSame('10.00', (string) $product->fresh()->stock);
    }
}
