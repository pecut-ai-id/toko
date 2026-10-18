<?php

namespace Tests\Feature\Payments;

use App\Enums\BusinessRole;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\Concerns\CreatesSales;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Pembatalan pembayaran (void) dan revisi D-023.
 *
 * Sebelumnya dokumen yang sudah dibayar terkunci: pembatalannya ditolak
 * tanpa jalan keluar. Sekarang pembayarannya bisa di-void lebih dulu,
 * barisnya tetap tersimpan sebagai riwayat, lalu dokumennya bisa
 * dibatalkan seperti biasa.
 */
class PaymentVoidTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, CreatesSales, ManagesStock, RefreshDatabase;

    /**
     * Penjualan dengan satu pembayaran, dibuat lewat endpoint HTTP.
     *
     * @return array{0: Sale, 1: Product}
     */
    private function paidSale(User $user, Business $business, string $quantity = '2', string $unitPrice = '10.000', string $paid = '20.000'): array
    {
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => $quantity, 'unit_price' => $unitPrice],
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => $paid,
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        return [$sale->fresh(), $product];
    }

    /**
     * Pembelian yang sudah diterima dan dibayar, lewat endpoint HTTP.
     *
     * @return array{0: Purchase, 1: Product}
     */
    private function paidPurchase(User $user, Business $business, string $quantity = '10', string $unitCost = '50.000', string $paid = '500.000'): array
    {
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => $quantity, 'unit_cost' => $unitCost],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase))->assertRedirect();

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => $paid,
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        return [$purchase->fresh(), $product];
    }

    public function test_voiding_a_sale_payment_restores_the_receivable(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business);

        $this->assertSame('20000.00', $sale->paidAmount());
        $this->assertSame('0.00', $sale->dueAmount());

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)
            ->post(route('payments.void', $payment), ['reason' => 'Salah input nominal'])
            ->assertRedirect(route('sales.show', $sale));

        $fresh = $sale->fresh();

        $this->assertSame('0.00', $fresh->paidAmount());
        $this->assertSame('20000.00', $fresh->dueAmount());
        $this->assertTrue($fresh->canRecordPayment());
        $this->assertTrue($fresh->canBeCancelled());
    }

    public function test_voiding_a_purchase_payment_restores_the_supplier_debt(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$purchase] = $this->paidPurchase($user, $business);

        $this->assertSame('0.00', $purchase->dueAmount());

        $payment = $purchase->payments()->firstOrFail();

        $this->actingAs($user)
            ->post(route('payments.void', $payment), ['reason' => 'Transfer gagal'])
            ->assertRedirect(route('purchases.show', $purchase));

        $fresh = $purchase->fresh();

        $this->assertSame('0.00', $fresh->paidAmount());
        $this->assertSame('500000.00', $fresh->dueAmount());
        $this->assertTrue($fresh->canBeCancelled());
    }

    public function test_a_voided_payment_row_is_kept_with_its_reason_and_actor(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business);

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Salah input nominal',
        ]);

        $fresh = $payment->fresh();

        // Barisnya tidak dihapus: jejaknya tetap ada, hanya berhenti dihitung.
        $this->assertDatabaseCount('payments', 1);
        $this->assertTrue($fresh->isVoided());
        $this->assertNotNull($fresh->voided_at);
        $this->assertSame($user->getKey(), $fresh->voided_by);
        $this->assertSame('Salah input nominal', $fresh->void_reason);
        $this->assertSame('20000.00', (string) $fresh->amount);
    }

    public function test_a_void_requires_a_reason(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business);

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)
            ->from(route('sales.show', $sale))
            ->post(route('payments.void', $payment), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($payment->fresh()->isVoided());
    }

    public function test_a_payment_cannot_be_voided_twice(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business);

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)
            ->post(route('payments.void', $payment), ['reason' => 'Salah input nominal'])
            ->assertRedirect();

        $this->actingAs($user)
            ->from(route('sales.show', $sale))
            ->post(route('payments.void', $payment), ['reason' => 'Dibatalkan dua kali'])
            ->assertSessionHasErrors('payment');

        $this->assertSame('Salah input nominal', $payment->fresh()->void_reason);
    }

    public function test_a_payment_on_an_already_cancelled_document_cannot_be_voided(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business);

        // Keadaan ini tidak bisa dicapai lewat UI (pembatalan ditolak selama
        // masih ada pembayaran berlaku), jadi penjaganya diuji langsung.
        $sale->forceFill(['status' => SaleStatus::Cancelled])->save();

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)
            ->from(route('sales.show', $sale))
            ->post(route('payments.void', $payment), ['reason' => 'Salah input nominal'])
            ->assertSessionHasErrors('payment');

        $this->assertFalse($payment->fresh()->isVoided());
    }

    public function test_staff_cannot_void_payments(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        [$sale] = $this->paidSale($owner, $business);

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($staff)
            ->post(route('payments.void', $payment), ['reason' => 'Salah input nominal'])
            ->assertForbidden();

        $this->assertFalse($payment->fresh()->isVoided());
    }

    public function test_a_user_cannot_void_a_foreign_payment(): void
    {
        [$owner, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($owner, $business);

        $payment = $sale->payments()->firstOrFail();

        [$outsider] = $this->businessWithUser();

        $this->actingAs($outsider)
            ->post(route('payments.void', $payment), ['reason' => 'Salah input nominal'])
            ->assertForbidden();

        $this->assertFalse($payment->fresh()->isVoided());
    }

    public function test_a_sale_can_be_cancelled_once_its_payments_are_voided(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale, $product] = $this->paidSale($user, $business, '2', '10.000', '20.000');

        $this->assertSame('18.00', (string) $product->fresh()->stock);

        // Tanpa void, pembatalan ditolak.
        $this->actingAs($user)
            ->from(route('sales.show', $sale))
            ->post(route('sales.cancel', $sale))
            ->assertSessionHasErrors('sale');

        $this->assertTrue($sale->fresh()->status->isCompleted());

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Pelanggan membatalkan pesanan',
        ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('sales.cancel', $sale))
            ->assertRedirect(route('sales.show', $sale));

        $fresh = $sale->fresh();

        $this->assertTrue($fresh->status->isCancelled());
        $this->assertSame('0.00', $fresh->dueAmount(), 'Dokumen yang dibatalkan tidak punya sisa.');
        $this->assertSame('20.00', (string) $product->fresh()->stock, 'Stok dikembalikan lewat reversal.');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_a_purchase_can_be_cancelled_once_its_payments_are_voided(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$purchase, $product] = $this->paidPurchase($user, $business);

        $this->assertSame('10.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.cancel', $purchase))
            ->assertSessionHasErrors('purchase');

        $payment = $purchase->payments()->firstOrFail();

        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Barang dikembalikan ke supplier',
        ])->assertRedirect();

        $this->actingAs($user)
            ->post(route('purchases.cancel', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertTrue($purchase->fresh()->status->isCancelled());
        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertSame('0.00', $purchase->fresh()->dueAmount());
    }

    public function test_the_full_amount_can_be_paid_again_after_a_void(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business, '2', '10.000', '20.000');

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Salah input nominal',
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '20.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $fresh = $sale->fresh();

        $this->assertSame('20000.00', $fresh->paidAmount());
        $this->assertSame('0.00', $fresh->dueAmount());
        $this->assertSame(2, $fresh->payments()->count(), 'Baris void tetap ada sebagai riwayat.');
    }

    public function test_the_show_page_marks_a_voided_payment_and_hides_its_actions(): void
    {
        [$user, $business] = $this->businessWithUser();
        [$sale] = $this->paidSale($user, $business);

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertSee('Alasan pembatalan');

        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Salah input nominal',
        ]);

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertSee('Dibatalkan')
            ->assertSee('Salah input nominal')
            ->assertDontSee('Alasan pembatalan');
    }

    public function test_the_customer_receivable_ignores_voided_payments(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $customer = Customer::factory()->forBusiness($business)->create();

        $this->actingAs($user)->post(route('sales.store'), [
            'customer_id' => $customer->getKey(),
            'sold_at' => '2026-10-18',
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '5', 'unit_price' => '85.000'],
            ],
        ])->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '200.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertSee('Rp225.000');

        $payment = $sale->payments()->firstOrFail();

        $this->actingAs($user)->post(route('payments.void', $payment), [
            'reason' => 'Salah input nominal',
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertSee('Rp425.000')
            ->assertDontSee('Rp225.000');
    }
}
