<?php

namespace Tests\Feature\Purchases;

use App\Enums\BusinessRole;
use App\Enums\PurchaseStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\TestCase;

class PurchaseManagementTest extends TestCase
{
    use CreatesBusinessUsers, RefreshDatabase;

    public function test_guest_cannot_access_purchases(): void
    {
        $this->get(route('purchases.index'))->assertRedirect(route('login'));
    }

    public function test_owner_creates_a_draft_purchase_with_number_and_totals(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $productA = Product::factory()->forBusiness($business)->create();
        $productB = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->getKey(),
                'purchased_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $productA->getKey(), 'quantity' => '10', 'unit_cost' => '50.000'],
                    ['product_id' => $productB->getKey(), 'quantity' => '2', 'unit_cost' => '1.275.000,50'],
                ],
            ])
            ->assertRedirect();

        $purchase = Purchase::with('items')->latest('id')->firstOrFail();

        $this->assertSame('PB-20261018-0001', $purchase->number);
        $this->assertSame($business->getKey(), $purchase->business_id);
        $this->assertTrue($purchase->status->isDraft());

        $items = $purchase->items->sortBy('id')->values();
        $this->assertSame('10.00', (string) $items[0]->quantity);
        $this->assertSame('50000.00', (string) $items[0]->unit_cost);
        $this->assertSame('500000.00', (string) $items[0]->subtotal);
        $this->assertSame('1275000.50', (string) $items[1]->unit_cost);
        $this->assertSame('2550001.00', (string) $items[1]->subtotal);
        $this->assertSame('3050001.00', (string) $purchase->total);
    }

    public function test_a_draft_purchase_does_not_change_stock(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->getKey(),
                'purchased_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '10', 'unit_cost' => '50000'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_document_numbers_are_sequential_per_business_and_per_day(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        foreach (['2026-10-18', '2026-10-18', '2026-10-19'] as $date) {
            $this->actingAs($user)->post(route('purchases.store'), [
                'supplier_id' => $supplier->getKey(),
                'purchased_at' => $date,
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '1', 'unit_cost' => '1000'],
                ],
            ])->assertRedirect();
        }

        $numbers = Purchase::query()->orderBy('id')->pluck('number')->all();

        $this->assertSame([
            'PB-20261018-0001',
            'PB-20261018-0002',
            'PB-20261019-0001',
        ], $numbers);
    }

    public function test_blank_item_rows_from_the_form_are_ignored(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->getKey(),
                'purchased_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '3', 'unit_cost' => '10.000'],
                    ['product_id' => '', 'quantity' => '', 'unit_cost' => ''],
                ],
            ])
            ->assertRedirect();

        $purchase = Purchase::with('items')->latest('id')->firstOrFail();

        $this->assertCount(1, $purchase->items);
        $this->assertSame('30000.00', (string) $purchase->total);
    }

    public function test_a_purchase_requires_a_supplier_and_at_least_one_item(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'supplier_id' => '',
                'purchased_at' => '',
                'items' => [],
            ])
            ->assertSessionHasErrors(['supplier_id', 'purchased_at', 'items']);

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_supplier_and_product_must_belong_to_the_active_business(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $foreignSupplier = Supplier::factory()->forBusiness($otherBusiness)->create();
        $foreignProduct = Product::factory()->forBusiness($otherBusiness)->create();

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'supplier_id' => $foreignSupplier->getKey(),
                'purchased_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $foreignProduct->getKey(), 'quantity' => '1', 'unit_cost' => '1000'],
                ],
            ])
            ->assertSessionHasErrors(['supplier_id', 'items.0.product_id']);

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_staff_can_create_and_view_purchases(): void
    {
        [$staff, $business] = $this->businessWithUser(BusinessRole::Staff);
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($staff)
            ->get(route('purchases.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->getKey(),
                'purchased_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '1', 'unit_cost' => '1000'],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('purchases', 1);
    }

    public function test_a_user_cannot_view_a_foreign_purchase(): void
    {
        [$user] = $this->businessWithUser();
        [, $otherBusiness] = $this->businessWithUser();

        $foreign = Purchase::factory()->forBusiness($otherBusiness)->create();

        $this->actingAs($user)
            ->get(route('purchases.show', $foreign))
            ->assertForbidden();
    }

    public function test_purchases_are_scoped_to_the_active_business(): void
    {
        [$user, $business] = $this->businessWithUser();
        $own = Purchase::factory()->forBusiness($business)->create();
        $foreign = Purchase::factory()->forBusiness(Business::factory()->create())->create();

        $this->actingAs($user)
            ->get(route('purchases.index'))
            ->assertOk()
            ->assertSee($own->number)
            ->assertDontSee($foreign->number);
    }

    public function test_purchase_status_filter_works(): void
    {
        [$user, $business] = $this->businessWithUser();

        Purchase::factory()->forBusiness($business)->create(['number' => 'PB-DRAFT-1']);
        Purchase::factory()->forBusiness($business)->create([
            'number' => 'PB-RECEIVED-1',
            'status' => PurchaseStatus::Received,
        ]);

        $this->actingAs($user)
            ->get(route('purchases.index', ['status' => 'received']))
            ->assertOk()
            ->assertSee('PB-RECEIVED-1')
            ->assertDontSee('PB-DRAFT-1');
    }

    public function test_unpaid_filter_shows_only_received_purchases_with_a_balance(): void
    {
        [$user, $business] = $this->businessWithUser();

        Purchase::factory()->forBusiness($business)->create([
            'number' => 'PB-BELUM-1',
            'status' => PurchaseStatus::Received,
            'total' => '100000.00',
        ]);

        $paid = Purchase::factory()->forBusiness($business)->create([
            'number' => 'PB-LUNAS-1',
            'status' => PurchaseStatus::Received,
            'total' => '100000.00',
        ]);

        // Draf belum menimbulkan utang, jadi tidak pernah "belum lunas".
        Purchase::factory()->forBusiness($business)->create([
            'number' => 'PB-DRAFT-1',
            'status' => PurchaseStatus::Draft,
            'total' => '100000.00',
        ]);

        Payment::factory()->forPurchase($paid)->create(['amount' => '100000.00']);

        $this->actingAs($user)
            ->get(route('purchases.index', ['pembayaran' => 'belum']))
            ->assertOk()
            ->assertSee('PB-BELUM-1')
            ->assertDontSee('PB-LUNAS-1')
            ->assertDontSee('PB-DRAFT-1');

        $this->actingAs($user)
            ->get(route('purchases.index', ['pembayaran' => 'sudah']))
            ->assertOk()
            ->assertSee('PB-LUNAS-1')
            ->assertDontSee('PB-BELUM-1')
            ->assertSee('pembayaran=sudah');
    }

    public function test_unpaid_filter_ignores_voided_and_cancelled_purchases(): void
    {
        [$user, $business] = $this->businessWithUser();

        $revoked = Purchase::factory()->forBusiness($business)->create([
            'number' => 'PB-VOID-1',
            'status' => PurchaseStatus::Received,
            'total' => '50000.00',
        ]);
        Payment::factory()->forPurchase($revoked)->voided($user)->create(['amount' => '50000.00']);

        Purchase::factory()->forBusiness($business)->create([
            'number' => 'PB-BATAL-1',
            'status' => PurchaseStatus::Cancelled,
            'total' => '50000.00',
        ]);

        $this->actingAs($user)
            ->get(route('purchases.index', ['pembayaran' => 'belum']))
            ->assertOk()
            ->assertSee('PB-VOID-1')
            ->assertDontSee('PB-BATAL-1');
    }
}
