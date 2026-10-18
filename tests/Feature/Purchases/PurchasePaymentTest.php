<?php

namespace Tests\Feature\Purchases;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\TestCase;

class PurchasePaymentTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, RefreshDatabase;

    /**
     * Pembelian yang sudah diterima lewat endpoint HTTP.
     *
     * @param  array<int, array{product: Product, quantity: int|string, unit_cost: int|string}>  $lines
     */
    private function receivedPurchase(User $user, Business $business, array $lines): Purchase
    {
        $purchase = $this->draftPurchase($user, $business, $lines);

        $this->actingAs($user)
            ->post(route('purchases.receive', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        return $purchase->fresh();
    }

    public function test_utang_appears_only_after_receiving(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $draft = $purchase->fresh();

        $this->assertSame('500000.00', $draft->totalAmount());
        $this->assertSame('0.00', $draft->paidAmount());
        $this->assertSame('0.00', $draft->dueAmount(), 'Draf belum menimbulkan utang.');
        $this->assertFalse($draft->canRecordPayment());

        $this->actingAs($user)->post(route('purchases.receive', $purchase));

        $received = $purchase->fresh();

        $this->assertTrue($received->isOpen());
        $this->assertSame('0.00', $received->paidAmount());
        $this->assertSame('500000.00', $received->dueAmount());
        $this->assertTrue($received->canRecordPayment());
    }

    public function test_a_draft_purchase_cannot_be_paid(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '100.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_full_payment_settles_the_supplier_debt(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '500.000',
                'paid_at' => '2026-10-18',
                'method' => 'Transfer',
                'reference' => 'TRF-001',
            ])
            ->assertRedirect(route('purchases.show', $purchase));

        $fresh = $purchase->fresh();

        $this->assertSame('500000.00', $fresh->paidAmount());
        $this->assertSame('0.00', $fresh->dueAmount());
        $this->assertFalse($fresh->canRecordPayment());

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('500000.00', (string) $payment->amount);
        $this->assertSame(Purchase::class, $payment->payable_type);
        $this->assertSame($purchase->getKey(), $payment->payable_id);
        $this->assertSame($business->getKey(), $payment->business_id);
        $this->assertSame($user->getKey(), $payment->user_id);
        $this->assertNull($payment->voided_at);
    }

    public function test_a_partial_payment_leaves_the_remaining_debt(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '200.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $fresh = $purchase->fresh();

        $this->assertSame('200000.00', $fresh->paidAmount());
        $this->assertSame('300000.00', $fresh->dueAmount());
        $this->assertTrue($fresh->canRecordPayment());

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '300.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->assertSame('500000.00', $purchase->fresh()->paidAmount());
        $this->assertSame('0.00', $purchase->fresh()->dueAmount());
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_overpayment_of_the_remaining_debt_is_rejected(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '200.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '400.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('300000.00', $purchase->fresh()->dueAmount());
    }

    public function test_a_zero_payment_is_rejected_by_validation(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '0',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_payment_on_a_cancelled_purchase_is_refused_by_the_service(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.cancel', $purchase));

        $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '100.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_may_record_purchase_payments(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($owner, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($staff)
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '100.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('100000.00', $purchase->fresh()->paidAmount());
    }

    public function test_a_user_cannot_pay_a_foreign_purchase(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($owner, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        [$outsider] = $this->businessWithUser();

        $this->actingAs($outsider)
            ->post(route('purchases.payments.store', $purchase), [
                'amount' => '100.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_the_show_page_offers_the_payment_form_while_debt_remains(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)
            ->get(route('purchases.show', $purchase))
            ->assertSee('Sisa utang')
            ->assertSee('Rp500.000')
            ->assertSee('Catat pembayaran');

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '500.000',
            'paid_at' => '2026-10-18',
        ]);

        $this->actingAs($user)
            ->get(route('purchases.show', $purchase))
            ->assertDontSee('Catat pembayaran');
    }

    public function test_the_index_shows_paid_and_remaining_debt_per_purchase(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '200.000',
            'paid_at' => '2026-10-18',
        ]);

        $this->actingAs($user)
            ->get(route('purchases.index'))
            ->assertSee('Dibayar')
            ->assertSee('Sisa utang')
            ->assertSee('Rp200.000')
            ->assertSee('Rp300.000');
    }
}
