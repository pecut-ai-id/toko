<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

class CustomerDetailTest extends TestCase
{
    use CreatesBusinessUsers, ManagesStock, RefreshDatabase;

    public function test_guest_cannot_view_a_customer_detail_page(): void
    {
        $customer = Customer::factory()->create();

        $this->get(route('customers.show', $customer))->assertRedirect(route('login'));
    }

    public function test_customer_detail_shows_contact_information(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create([
            'name' => 'Toko Berkah',
            'phone' => '08987654321',
            'address' => 'Jl. Merdeka 10',
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Toko Berkah')
            ->assertSee('08987654321')
            ->assertSee('Jl. Merdeka 10');
    }

    public function test_a_user_cannot_view_a_foreign_customer_detail_page(): void
    {
        [$user] = $this->businessWithUser();
        $foreign = Customer::factory()->create();

        $this->actingAs($user)
            ->get(route('customers.show', $foreign))
            ->assertForbidden();
    }

    public function test_customer_detail_shows_the_receivable_from_sales_and_payments(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Berkah']);
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '15', 'unit_price' => '85.000'],
                ],
                'paid_amount' => '1.000.000',
            ])
            ->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Rp275.000')
            ->assertSee($sale->number);
    }

    public function test_a_cancelled_sale_does_not_count_toward_the_customer_receivable(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        // Penjualan A: Rp1.275.000, lalu dibatalkan.
        $cancelled = $this->saleFor($user, $customer, $product, '15', '85.000');
        $this->actingAs($user)->post(route('sales.cancel', $cancelled))->assertRedirect();

        // Penjualan B: Rp500.000, tetap terbuka.
        $open = $this->saleFor($user, $customer, $product, '5', '100.000');

        $this->assertSame('0.00', $cancelled->fresh()->dueAmount());
        $this->assertSame('500000.00', $open->fresh()->dueAmount());

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Rp500.000')
            // Tidak boleh ikut menjumlahkan penjualan yang sudah dibatalkan.
            ->assertDontSee('Rp1.775.000');
    }

    private function saleFor(
        User $user,
        Customer $customer,
        Product $product,
        string $quantity,
        string $unitPrice,
    ): Sale {
        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => $customer->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    [
                        'product_id' => $product->getKey(),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ],
                ],
            ])
            ->assertRedirect();

        return Sale::latest('id')->firstOrFail();
    }
}
