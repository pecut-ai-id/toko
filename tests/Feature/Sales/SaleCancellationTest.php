<?php

namespace Tests\Feature\Sales;

use App\Enums\BusinessRole;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesSales;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

class SaleCancellationTest extends TestCase
{
    use CreatesBusinessUsers, CreatesSales, ManagesStock, RefreshDatabase;

    public function test_cancelling_a_sale_returns_stock_through_a_traced_reversal(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 6, 'unit_price' => '10.000'],
        ]);

        $this->assertSame('14.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('sales.cancel', $sale))
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame('20.00', (string) $product->fresh()->stock);

        $fresh = $sale->fresh();
        $this->assertTrue($fresh->status->isCancelled());
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame($user->getKey(), $fresh->cancelled_by);

        // Penjualan tidak dihapus, dan reversal tercatat sebagai baris baru.
        $this->assertDatabaseHas('sales', ['id' => $sale->getKey()]);

        $reversal = InventoryMovement::query()
            ->where('product_id', $product->getKey())
            ->where('reason', 'like', 'Pembatalan penjualan %')
            ->sole();

        $this->assertSame('6.00', (string) $reversal->quantity);
        $this->assertSame('20.00', (string) $reversal->balance_after);
    }

    public function test_a_cancelled_sale_cannot_be_cancelled_again(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('sales.cancel', $sale));
        $this->actingAs($user)->post(route('sales.cancel', $sale))->assertSessionHasErrors('sale');

        // Stok tidak dikembalikan dua kali.
        $this->assertSame('20.00', (string) $product->fresh()->stock);
    }

    public function test_only_owner_or_admin_may_cancel_a_sale(): void
    {
        [$owner, $business] = $this->businessWithUser(BusinessRole::Owner);
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $owner);

        $sale = $this->createSale($owner, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        // Staff melihat tombolnya tidak ada, tapi server tetap harus menolak.
        $this->actingAs($staff)->post(route('sales.cancel', $sale))->assertForbidden();

        $this->assertTrue($sale->fresh()->status->isCompleted());

        $this->actingAs($owner)->post(route('sales.cancel', $sale))->assertRedirect();

        $this->assertTrue($sale->fresh()->status->isCancelled());
    }

    public function test_a_sale_that_already_received_payment_cannot_be_cancelled(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '5.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect(route('sales.show', $sale));

        $this->actingAs($user)
            ->post(route('sales.cancel', $sale))
            ->assertSessionHasErrors('sale');

        $fresh = $sale->fresh();
        $this->assertTrue($fresh->status->isCompleted());
        $this->assertSame('18.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_a_user_cannot_cancel_a_foreign_sale(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $foreign = Sale::factory()->forBusiness($otherBusiness)->create();

        $this->actingAs($user)->post(route('sales.cancel', $foreign))->assertForbidden();
    }

    public function test_the_show_page_hides_the_cancel_action_for_a_paid_sale(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->get(route('sales.show', $sale))->assertSee('Batalkan penjualan');

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '20.000',
            'paid_at' => '2026-10-18',
        ]);

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertDontSee('Batalkan penjualan')
            ->assertSee('masih punya pembayaran yang berlaku')
            // Jalan keluar baru (D-026): pembayaran bisa di-void dulu.
            ->assertSee('Alasan pembatalan')
            ->assertSee('Batalkan');
    }

    public function test_a_cancelled_sale_reports_no_outstanding_receivable(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 5, 'unit_price' => '85.000'],
        ]);

        $this->assertSame('425000.00', $sale->fresh()->dueAmount());

        $this->actingAs($user)->post(route('sales.cancel', $sale))->assertRedirect();

        $fresh = $sale->fresh();

        $this->assertSame('0.00', $fresh->dueAmount(), 'Penjualan yang dibatalkan tidak punya sisa piutang.');
        $this->assertFalse($fresh->canRecordPayment());
        $this->assertFalse($fresh->canBeCancelled());
    }

    public function test_cancelling_a_sale_does_not_touch_other_products(): void
    {
        [$user, $business] = $this->businessWithUser();
        $productA = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);
        $productB = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $productA, 'quantity' => 4, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('sales.cancel', $sale));

        $this->assertSame('20.00', (string) $productA->fresh()->stock);
        $this->assertSame('20.00', (string) $productB->fresh()->stock);
    }
}
