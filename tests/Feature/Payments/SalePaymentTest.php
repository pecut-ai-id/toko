<?php

namespace Tests\Feature\Payments;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesSales;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

class SalePaymentTest extends TestCase
{
    use CreatesBusinessUsers, CreatesSales, ManagesStock, RefreshDatabase;

    public function test_a_full_payment_clears_the_remaining_receivable(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 15, 'unit_price' => '85.000'],
        ]);

        $this->assertSame('1275000.00', $sale->totalAmount());
        $this->assertSame('0.00', $sale->paidAmount());
        $this->assertSame('1275000.00', $sale->dueAmount());

        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), [
                'amount' => '1.275.000',
                'paid_at' => '2026-10-18',
                'method' => 'Transfer',
                'reference' => 'TRF-001',
            ])
            ->assertRedirect(route('sales.show', $sale));

        $fresh = $sale->fresh();

        $this->assertSame('1275000.00', $fresh->paidAmount());
        $this->assertSame('0.00', $fresh->dueAmount());
        $this->assertFalse($fresh->canRecordPayment());

        $payment = Payment::sole();
        $this->assertSame('1275000.00', (string) $payment->amount);
        $this->assertSame('Transfer', $payment->method);
        $this->assertSame('TRF-001', $payment->reference);
        $this->assertSame($user->getKey(), $payment->user_id);
        $this->assertSame($business->getKey(), $payment->business_id);
        $this->assertSame(Sale::class, $payment->payable_type);
        $this->assertSame($sale->getKey(), $payment->payable_id);
    }

    public function test_partial_payments_accumulate_and_the_last_one_clears_the_due(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 15, 'unit_price' => '85.000'],
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '1.000.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->assertSame('1000000.00', $sale->fresh()->paidAmount());
        $this->assertSame('275000.00', $sale->fresh()->dueAmount());

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '275.000',
            'paid_at' => '2026-10-19',
        ])->assertRedirect();

        $this->assertSame('1275000.00', $sale->fresh()->paidAmount());
        $this->assertSame('0.00', $sale->fresh()->dueAmount());
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_a_payment_larger_than_the_outstanding_balance_is_rejected(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        // Sisa Rp20.000, lalu bayar Rp25.000.
        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), [
                'amount' => '25.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('20000.00', $sale->fresh()->dueAmount());
    }

    public function test_the_balance_is_rechecked_after_each_partial_payment(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => '15.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        // Sisa hanya Rp5.000 sekarang.
        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), [
                'amount' => '6.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('5000.00', $sale->fresh()->dueAmount());
    }

    public function test_zero_and_negative_payments_are_rejected(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        foreach (['0', '-5.000'] as $amount) {
            $this->actingAs($user)
                ->post(route('sales.payments.store', $sale), [
                    'amount' => $amount,
                    'paid_at' => '2026-10-18',
                ])
                ->assertSessionHasErrors('amount');
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_cancelled_sale_cannot_receive_payment(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('sales.cancel', $sale))->assertRedirect();

        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), [
                'amount' => '5.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_staff_may_record_a_payment(): void
    {
        [$owner, $business] = $this->businessWithUser(BusinessRole::Owner);
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $owner);

        $sale = $this->createSale($owner, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($staff)->post(route('sales.payments.store', $sale), [
            'amount' => '10.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('10000.00', $sale->fresh()->dueAmount());
    }

    public function test_a_payment_cannot_be_recorded_on_a_foreign_sale(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $foreign = Sale::factory()->forBusiness($otherBusiness)->create(['total' => '10000.00']);

        $this->actingAs($user)
            ->post(route('sales.payments.store', $foreign), [
                'amount' => '1.000',
                'paid_at' => '2026-10-18',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_initial_payment_can_be_recorded_when_the_sale_is_created(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 15, 'unit_price' => '85.000'],
        ], '2026-10-18', [
            'paid_amount' => '500.000',
            'paid_at' => '2026-10-18',
            'payment_method' => 'Tunai',
        ]);

        $this->assertSame('500000.00', $sale->fresh()->paidAmount());
        $this->assertSame('775000.00', $sale->fresh()->dueAmount());
        $this->assertSame('Tunai', Payment::sole()->method);
    }

    public function test_an_initial_payment_larger_than_the_total_rolls_back_the_whole_sale(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'paid_amount' => '30.000',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '2', 'unit_price' => '10.000'],
                ],
            ])
            ->assertSessionHasErrors('sale');

        // Tidak boleh ada sisa apa pun: header, item, gerakan stok, dan
        // pembayaran semuanya dibatalkan bersama.
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('50.00', (string) $product->fresh()->stock);
    }

    public function test_payments_are_scoped_to_the_active_business(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $own = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)->post(route('sales.payments.store', $own), [
            'amount' => '5.000',
            'paid_at' => '2026-10-18',
            'reference' => 'OWN-REF',
        ]);

        // Pembayaran milik bisnis lain tidak muncul di halaman penjualan.
        $otherBusiness = Business::factory()->create();
        $foreignSale = Sale::factory()->forBusiness($otherBusiness)->create(['total' => '10000.00']);
        Payment::factory()->forSale($foreignSale)->create(['reference' => 'FOREIGN-REF']);

        $this->actingAs($user)
            ->get(route('sales.show', $own))
            ->assertOk()
            ->assertSee('OWN-REF')
            ->assertDontSee('FOREIGN-REF');

        $this->actingAs($user)
            ->get(route('sales.show', $foreignSale))
            ->assertForbidden();
    }

    public function test_the_index_shows_paid_and_outstanding_amounts(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 15, 'unit_price' => '85.000'],
        ], '2026-10-18', ['paid_amount' => '1.000.000']);

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee($sale->number)
            ->assertSee('Rp1.275.000')
            ->assertSee('Rp1.000.000')
            ->assertSee('Rp275.000');
    }
}
