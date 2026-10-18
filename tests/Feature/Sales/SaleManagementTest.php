<?php

namespace Tests\Feature\Sales;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesSales;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

class SaleManagementTest extends TestCase
{
    use CreatesBusinessUsers, CreatesSales, ManagesStock, RefreshDatabase;

    public function test_guest_cannot_access_sales(): void
    {
        $this->get(route('sales.index'))->assertRedirect(route('login'));
    }

    public function test_staff_can_view_and_create_sales(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create(),
            50,
            $staff,
        );

        $this->actingAs($staff)->get(route('sales.index'))->assertOk();

        $this->actingAs($staff)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '1', 'unit_price' => '85.000'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_it_creates_a_sale_with_number_and_server_side_totals(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();

        $productA = $this->setStock(
            Product::factory()->forBusiness($business)->create(['cost_price' => 60000]),
            50,
            $user,
        );
        $productB = $this->setStock(
            Product::factory()->forBusiness($business)->create(['cost_price' => 10000]),
            50,
            $user,
        );

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $productA->getKey(), 'quantity' => '10', 'unit_price' => '85.000'],
                    ['product_id' => $productB->getKey(), 'quantity' => '2', 'unit_price' => '1.275.000,50'],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::with('items')->latest('id')->firstOrFail();

        $this->assertSame('PJ-20261018-0001', $sale->number);
        $this->assertSame($business->getKey(), $sale->business_id);
        $this->assertSame($customer->getKey(), $sale->customer_id);
        $this->assertTrue($sale->status->isCompleted());

        $items = $sale->items->sortBy('id')->values();
        $this->assertSame('85000.00', (string) $items[0]->unit_price);
        $this->assertSame('850000.00', (string) $items[0]->subtotal);
        $this->assertSame('1275000.50', (string) $items[1]->unit_price);
        $this->assertSame('2550001.00', (string) $items[1]->subtotal);
        $this->assertSame('3400001.00', (string) $sale->total);
    }

    public function test_totals_from_the_browser_are_ignored(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create(),
            50,
            $user,
        );

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'subtotal' => '1.00',
                'total' => '1.00',
                'items' => [
                    [
                        'product_id' => $product->getKey(),
                        'quantity' => '4',
                        'unit_price' => '25.000',
                        'subtotal' => '1.00',
                    ],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::with('items')->latest('id')->firstOrFail();

        $this->assertSame('100000.00', (string) $sale->total);
        $this->assertSame('100000.00', (string) $sale->items->first()->subtotal);
    }

    public function test_prices_are_snapshotted_so_later_price_changes_do_not_rewrite_history(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create([
                'sell_price' => 85000,
                'cost_price' => 60000,
            ]),
            50,
            $user,
        );

        $this->actingAs($user)->post(route('sales.store'), [
            'customer_id' => $customer->getKey(),
            'sold_at' => '2026-10-18',
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '5', 'unit_price' => '80.000'],
            ],
        ])->assertRedirect();

        // Harga produk berubah setelah transaksi.
        $product->update(['sell_price' => 99000, 'cost_price' => 70000]);

        $item = Sale::latest('id')->firstOrFail()->items()->firstOrFail();

        $this->assertSame('80000.00', (string) $item->unit_price, 'Harga jual harus tetap seperti saat transaksi.');
        $this->assertSame('60000.00', (string) $item->unit_cost, 'Harga pokok harus tetap seperti saat transaksi.');
    }

    public function test_unit_cost_comes_from_the_server_not_the_form(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create(['cost_price' => 60000]),
            50,
            $user,
        );

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    [
                        'product_id' => $product->getKey(),
                        'quantity' => '1',
                        'unit_price' => '85.000',
                        'unit_cost' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(
            '60000.00',
            (string) Sale::latest('id')->firstOrFail()->items()->firstOrFail()->unit_cost,
        );
    }

    public function test_document_numbers_are_sequential_per_business_and_per_day(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create(),
            50,
            $user,
        );

        foreach (['2026-10-18', '2026-10-18', '2026-10-19'] as $date) {
            $this->actingAs($user)->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => $date,
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '1', 'unit_price' => '1000'],
                ],
            ])->assertRedirect();
        }

        $this->assertSame([
            'PJ-20261018-0001',
            'PJ-20261018-0002',
            'PJ-20261019-0001',
        ], Sale::query()->orderBy('id')->pluck('number')->all());
    }

    public function test_blank_item_rows_from_the_form_are_ignored(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create(),
            50,
            $user,
        );

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '3', 'unit_price' => '10.000'],
                    ['product_id' => '', 'quantity' => '', 'unit_price' => ''],
                ],
            ])
            ->assertRedirect();

        $sale = Sale::with('items')->latest('id')->firstOrFail();

        $this->assertCount(1, $sale->items);
        $this->assertSame('30000.00', (string) $sale->total);
    }

    public function test_a_sale_requires_a_customer_and_at_least_one_item(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => '',
                'sold_at' => '',
                'items' => [],
            ])
            ->assertSessionHasErrors(['customer_id', 'sold_at', 'items']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_customer_and_product_must_belong_to_the_active_business(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $foreignCustomer = Customer::factory()->forBusiness($otherBusiness)->create();
        $foreignProduct = Product::factory()->forBusiness($otherBusiness)->create();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $foreignCustomer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $foreignProduct->getKey(), 'quantity' => '1', 'unit_price' => '1000'],
                ],
            ])
            ->assertSessionHasErrors(['customer_id', 'items.0.product_id']);

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_a_user_cannot_view_a_foreign_sale(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $foreign = Sale::factory()->forBusiness($otherBusiness)->create();

        $this->actingAs($user)->get(route('sales.show', $foreign))->assertForbidden();
    }

    public function test_sales_are_scoped_to_the_active_business(): void
    {
        [$user, $business] = $this->businessWithUser();
        $own = Sale::factory()->forBusiness($business)->create();
        $foreign = Sale::factory()->forBusiness(Business::factory()->create())->create();

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee($own->number)
            ->assertDontSee($foreign->number);
    }

    public function test_status_filter_works(): void
    {
        [$user, $business] = $this->businessWithUser();

        Sale::factory()->forBusiness($business)->create(['number' => 'PJ-COMPLETED-1']);
        Sale::factory()->forBusiness($business)->cancelled()->create(['number' => 'PJ-CANCELLED-1']);

        $this->actingAs($user)
            ->get(route('sales.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertSee('PJ-CANCELLED-1')
            ->assertDontSee('PJ-COMPLETED-1');
    }

    public function test_unpaid_filter_shows_only_sales_that_still_have_a_balance(): void
    {
        [$user, $business] = $this->businessWithUser();

        Sale::factory()->forBusiness($business)->create(['number' => 'PJ-BELUM-1', 'total' => '100000.00']);
        Sale::factory()->forBusiness($business)->create(['number' => 'PJ-SEBAGIAN-1', 'total' => '100000.00']);

        $paid = Sale::factory()->forBusiness($business)->create(['number' => 'PJ-LUNAS-1', 'total' => '100000.00']);

        Payment::factory()->forSale($paid)->create(['amount' => '100000.00']);

        $partial = Sale::query()->forBusiness($business)->where('number', 'PJ-SEBAGIAN-1')->sole();
        Payment::factory()->forSale($partial)->create(['amount' => '40000.00']);

        $this->actingAs($user)
            ->get(route('sales.index', ['pembayaran' => 'belum']))
            ->assertOk()
            ->assertSee('PJ-BELUM-1')
            ->assertSee('PJ-SEBAGIAN-1')
            ->assertDontSee('PJ-LUNAS-1');

        $this->actingAs($user)
            ->get(route('sales.index', ['pembayaran' => 'sudah']))
            ->assertOk()
            ->assertSee('PJ-LUNAS-1')
            ->assertDontSee('PJ-BELUM-1')
            ->assertSee('pembayaran=sudah');
    }

    public function test_unpaid_filter_ignores_voided_payments_and_cancelled_sales(): void
    {
        [$user, $business] = $this->businessWithUser();

        // Pembayaran yang di-void tidak lagi melunasi (D-026).
        $revoked = Sale::factory()->forBusiness($business)->create(['number' => 'PJ-VOID-1', 'total' => '50000.00']);
        Payment::factory()->forSale($revoked)->voided($user)->create(['amount' => '50000.00']);

        // Penjualan yang dibatalkan tidak punya tagihan lagi (D-025).
        Sale::factory()->forBusiness($business)->cancelled()->create(['number' => 'PJ-BATAL-1', 'total' => '50000.00']);

        $this->actingAs($user)
            ->get(route('sales.index', ['pembayaran' => 'belum']))
            ->assertOk()
            ->assertSee('PJ-VOID-1')
            ->assertDontSee('PJ-BATAL-1');

        $this->actingAs($user)
            ->get(route('sales.index', ['pembayaran' => 'sudah']))
            ->assertOk()
            ->assertDontSee('PJ-VOID-1')
            ->assertDontSee('PJ-BATAL-1');
    }

    public function test_an_unknown_payment_filter_is_ignored(): void
    {
        [$user, $business] = $this->businessWithUser();

        Sale::factory()->forBusiness($business)->create(['number' => 'PJ-SATU-1', 'total' => '10000.00']);

        $this->actingAs($user)
            ->get(route('sales.index', ['pembayaran' => 'ngawur']))
            ->assertOk()
            ->assertSee('PJ-SATU-1');
    }
}
