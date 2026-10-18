<?php

namespace Tests\Feature\Acceptance;

use App\Models\Business;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Uji penerimaan yang mengikuti requirements.md Bagian 7 apa adanya.
 *
 * Skenario: stok awal 100, pembelian diterima +20, penjualan 15 unit
 *
 * @ Rp85.000 (harga pokok Rp60.000), pembayaran pelanggan Rp1.000.000.
 */
class MvpAcceptanceTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, ManagesStock, RefreshDatabase;

    public function test_spec_section_7_acceptance_scenario(): void
    {
        [$user, $business] = $this->businessWithUser();

        $product = Product::factory()->forBusiness($business)->create([
            'sku' => 'KOPI-1',
            'name' => 'Kopi Arabika 1kg',
            'sell_price' => 85000,
            'cost_price' => 60000,
        ]);

        // Stok awal: 100 unit, dicatat lewat form penyesuaian stok di UI.
        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '100',
                'reason' => 'Stok awal',
            ])
            ->assertRedirect(route('products.show', $product));

        // Pembelian diterima: +20 unit.
        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 20, 'unit_cost' => '60.000'],
        ]);

        $this->actingAs($user)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('120.00', (string) $product->fresh()->stock);

        // Penjualan 15 unit @ Rp85.000.
        $customer = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Sebelah']);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '15', 'unit_price' => '85.000'],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::with('items')->latest('id')->firstOrFail();

        // Pembayaran pelanggan: Rp1.000.000 (parsial).
        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), [
                'amount' => '1.000.000',
                'paid_at' => '2026-10-18',
                'method' => 'Transfer',
            ])
            ->assertRedirect(route('sales.show', $sale));

        $sale->refresh();

        // --- Hasil yang diharapkan oleh requirements.md Bagian 7 ---------------

        $this->assertSame('105.00', (string) $product->fresh()->stock, 'Stok akhir = 100 + 20 - 15.');
        $this->assertSame('1275000.00', $sale->totalAmount(), 'Nilai penjualan = 15 x Rp85.000.');
        $this->assertSame('275000.00', $sale->dueAmount(), 'Sisa piutang = Rp1.275.000 - Rp1.000.000.');
        $this->assertSame('900000.00', $sale->costTotal(), 'HPP = 15 x Rp60.000.');
        $this->assertSame('375000.00', $sale->marginTotal(), 'Margin kotor = Rp1.275.000 - Rp900.000.');

        // Jejak yang bisa ditelusuri: setiap perubahan stok punya barisnya.
        $ledger = (float) InventoryMovement::query()
            ->where('product_id', $product->getKey())
            ->sum('quantity');

        $this->assertSame(105.0, round($ledger, 2), 'Total ledger stok harus sama dengan stok produk.');

        // Nama pelanggan dan nilai uang tampil sesuai format Indonesia.
        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Toko Sebelah')
            ->assertSee('Rp1.275.000')
            ->assertSee('Rp275.000')
            ->assertSee('Rp900.000')
            ->assertSee('Rp375.000');
    }

    public function test_selling_more_than_available_stock_leaves_no_partial_change(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 5, $user);

        $salesBefore = Sale::query()->count();
        $movementsBefore = InventoryMovement::query()->count();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '6', 'unit_price' => '85.000'],
                ],
            ])
            ->assertSessionHasErrors('sale');

        $this->assertSame('5.00', (string) $product->fresh()->stock);
        $this->assertSame($salesBefore, Sale::query()->count());
        $this->assertSame($movementsBefore, InventoryMovement::query()->count());
    }

    public function test_overpayment_is_rejected_per_the_agreed_rule(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '15', 'unit_price' => '85.000'],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), [
                'amount' => '1.500.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('1275000.00', $sale->fresh()->dueAmount());
    }

    public function test_cancellation_produces_a_correct_stock_reversal(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 100, $user);

        $sale = $this->createSaleFor($user, $business, $product, 15);

        $this->assertSame('85.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('sales.cancel', $sale))
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame('100.00', (string) $product->fresh()->stock);
        $this->assertTrue($sale->fresh()->status->isCancelled());

        $ledger = (float) InventoryMovement::query()
            ->where('product_id', $product->getKey())
            ->sum('quantity');

        $this->assertSame(100.0, round($ledger, 2));
    }

    public function test_cross_business_access_is_denied_everywhere(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $product = Product::factory()->forBusiness($otherBusiness)->create();
        $customer = Customer::factory()->forBusiness($otherBusiness)->create();
        $sale = Sale::factory()->forBusiness($otherBusiness)->create(['total' => '10000.00']);
        $purchase = Purchase::factory()->forBusiness($otherBusiness)->create([
            'status' => 'received',
            'total' => '10000.00',
        ]);
        $payment = Payment::factory()->forPayable($purchase)->create();

        $this->actingAs($user)->get(route('sales.index'))->assertOk()->assertDontSee($sale->number);
        $this->actingAs($user)->get(route('sales.show', $sale))->assertForbidden();
        $this->actingAs($user)->post(route('sales.cancel', $sale))->assertForbidden();
        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '1.000',
            'paid_at' => '2026-10-18',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '1.000',
            'paid_at' => '2026-10-18',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Coba-coba',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('products.stock-adjustments.store', $product), [
            'direction' => 'in',
            'quantity' => '10',
            'reason' => 'Coba-coba',
        ])->assertForbidden();
        $this->actingAs($user)->get(route('products.show', $product))->assertForbidden();
        $this->actingAs($user)->get(route('customers.show', $customer))->assertForbidden();

        $this->assertFalse($payment->fresh()->isVoided());

        // Bahkan input yang menunjuk data bisnis lain ditolak validasi.
        $ownProduct = Product::factory()->forBusiness($this->activeBusinessOf($user))->create();

        $this->actingAs($user)->post(route('sales.store'), [
            'customer_id' => $customer->getKey(),
            'sold_at' => '2026-10-18',
            'items' => [
                ['product_id' => $ownProduct->getKey(), 'quantity' => '1', 'unit_price' => '1.000'],
            ],
        ])->assertSessionHasErrors('customer_id');

        $this->assertSame(
            0,
            Payment::query()->where('business_id', $this->activeBusinessOf($user)->getKey())->count()
        );
        $this->assertSame(0, Sale::query()->where('business_id', $this->activeBusinessOf($user)->getKey())->count());
        $this->assertSame('0.00', (string) $ownProduct->fresh()->stock);
    }

    /**
     * Utang supplier: draf belum dihitung, penerimaan menimbulkan utang,
     * pembayaran menguranginya, dan void mengembalikannya.
     */
    public function test_supplier_debt_is_recorded_and_can_be_settled(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['cost_price' => 60000]);

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 20, 'unit_cost' => '60.000'],
        ]);

        $this->assertSame('0.00', $purchase->fresh()->dueAmount(), 'Draf belum menimbulkan utang.');

        $this->actingAs($user)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('1200000.00', $purchase->fresh()->dueAmount(), 'Utang = 20 x Rp60.000.');
        $this->assertSame('20.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '700.000',
                'paid_at' => '2026-10-18',
                'method' => 'Transfer',
            ])
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('500000.00', $purchase->fresh()->dueAmount());

        $payment = $purchase->payments()->firstOrFail();

        $this->actingAs($user)
            ->post(route('payments.void', $payment), ['reason' => 'Transfer gagal'])
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertTrue($payment->fresh()->isVoided());
        $this->assertSame('1200000.00', $purchase->fresh()->dueAmount(), 'Utang kembali penuh setelah void.');
        $this->assertDatabaseCount('payments', 1);
    }

    /**
     * Penjualan sederhana untuk skenario pembatalan.
     */
    private function createSaleFor(User $user, Business $business, Product $product, int $quantity): Sale
    {
        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => (string) $quantity, 'unit_price' => '85.000'],
                ],
            ])
            ->assertRedirect();

        return Sale::latest('id')->firstOrFail();
    }

    private function activeBusinessOf(User $user): Business
    {
        return $user->businesses()->firstOrFail();
    }
}
