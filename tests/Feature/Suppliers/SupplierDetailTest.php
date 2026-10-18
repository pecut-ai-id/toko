<?php

namespace Tests\Feature\Suppliers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\TestCase;

class SupplierDetailTest extends TestCase
{
    use CreatesBusinessUsers, RefreshDatabase;

    public function test_guest_cannot_view_a_supplier_detail_page(): void
    {
        $supplier = Supplier::factory()->create();

        $this->get(route('suppliers.show', $supplier))->assertRedirect(route('login'));
    }

    public function test_supplier_detail_shows_contacts_and_purchase_history(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create([
            'name' => 'CV Sumber Kopi',
            'phone' => '08123456789',
            'email' => 'sales@sumberkopi.test',
        ]);

        Purchase::factory()->forBusiness($business)->create([
            'supplier_id' => $supplier->getKey(),
            'number' => 'PB-20261018-0007',
            'total' => '500000.00',
        ]);

        $this->actingAs($user)
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('CV Sumber Kopi')
            ->assertSee('08123456789')
            ->assertSee('sales@sumberkopi.test')
            ->assertSee('PB-20261018-0007')
            ->assertSee('Rp500.000');
    }

    public function test_a_user_cannot_view_a_foreign_supplier_detail_page(): void
    {
        [$user] = $this->businessWithUser();
        $foreign = Supplier::factory()->create();

        $this->actingAs($user)
            ->get(route('suppliers.show', $foreign))
            ->assertForbidden();
    }

    public function test_the_detail_page_shows_the_remaining_supplier_debt(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create(['name' => 'CV Sumber Kopi']);
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => '2026-10-18',
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '10', 'unit_cost' => '50.000'],
            ],
        ])->assertRedirect();

        $purchase = Purchase::latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('purchases.receive', $purchase))->assertRedirect();

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '200.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Total utang berjalan')
            ->assertSeeInOrder(['Total utang berjalan', 'Rp300.000']);
    }

    public function test_the_debt_goes_back_up_when_a_payment_is_voided(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => '2026-10-18',
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '10', 'unit_cost' => '50.000'],
            ],
        ])->assertRedirect();

        $purchase = Purchase::latest('id')->firstOrFail();

        $this->actingAs($user)->post(route('purchases.receive', $purchase))->assertRedirect();

        $this->actingAs($user)->post(route('purchases.payments.store', $purchase), [
            'amount' => '200.000',
            'paid_at' => '2026-10-18',
        ])->assertRedirect();

        $payment = $purchase->payments()->firstOrFail();

        $this->actingAs($user)
            ->post(route('payments.void', $payment), ['reason' => 'Transfer gagal'])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSeeInOrder(['Total utang berjalan', 'Rp500.000'])
            ->assertDontSee('Rp300.000');
    }

    public function test_draft_purchases_are_not_counted_as_debt(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => '2026-10-18',
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '10', 'unit_cost' => '50.000'],
            ],
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSeeInOrder(['Total utang berjalan', 'Rp0']);
    }
}
