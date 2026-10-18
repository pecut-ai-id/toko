<?php

namespace Tests\Feature\Inventory;

use App\Enums\BusinessRole;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Penyesuaian stok manual (D-028).
 *
 * Sebelum ini, stok awal dan koreksi hitung hanya bisa dilakukan lewat
 * InventoryService dari kode/tinker — tidak ada jalan di UI. Sekarang
 * owner/admin punya formulirnya, dan hasilnya tetap satu baris pergerakan
 * stok yang tercatat beserta alasannya.
 */
class StockAdjustmentTest extends TestCase
{
    use CreatesBusinessUsers, ManagesStock, RefreshDatabase;

    public function test_an_owner_can_record_opening_stock(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->assertSame('0.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '120',
                'reason' => 'Stok awal',
            ])
            ->assertRedirect(route('products.show', $product));

        $this->assertSame('120.00', (string) $product->fresh()->stock);

        $movement = InventoryMovement::query()->firstOrFail();

        $this->assertSame('120.00', (string) $movement->quantity);
        $this->assertSame('120.00', (string) $movement->balance_after);
        $this->assertSame('Stok awal', $movement->reason);
        $this->assertSame($user->getKey(), $movement->user_id);
        $this->assertSame($business->getKey(), $movement->business_id);
        $this->assertNull($movement->source_type, 'Penyesuaian tidak menempel pada dokumen transaksi.');
    }

    public function test_an_outgoing_adjustment_reduces_stock(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'out',
                'quantity' => '3',
                'reason' => 'Barang rusak',
            ])
            ->assertRedirect(route('products.show', $product));

        $this->assertSame('17.00', (string) $product->fresh()->stock);

        $movement = InventoryMovement::query()->where('reason', 'Barang rusak')->firstOrFail();

        $this->assertSame('-3.00', (string) $movement->quantity);
        $this->assertSame('17.00', (string) $movement->balance_after);
    }

    public function test_an_adjustment_that_would_make_stock_negative_is_rejected(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 10, $user);

        $this->actingAs($user)
            ->from(route('products.show', $product))
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'out',
                'quantity' => '50',
                'reason' => 'Barang hilang',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame('10.00', (string) $product->fresh()->stock);
        $this->assertSame(0, InventoryMovement::query()->where('reason', 'Barang hilang')->count());
    }

    public function test_an_adjustment_requires_a_reason(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->from(route('products.show', $product))
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '10',
                'reason' => 'ab',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_a_zero_quantity_is_rejected(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->from(route('products.show', $product))
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '0',
                'reason' => 'Stok awal',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_indonesian_thousands_separator_is_understood(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '1.000',
                'reason' => 'Stok awal',
            ])
            ->assertRedirect();

        $this->assertSame('1000.00', (string) $product->fresh()->stock);
    }

    public function test_an_optional_date_is_used_for_the_movement(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '5',
                'reason' => 'Stok awal',
                'occurred_at' => '2026-10-01',
            ])
            ->assertRedirect();

        $movement = InventoryMovement::query()->firstOrFail();

        $this->assertSame('2026-10-01', $movement->occurred_at?->toDateString());
    }

    public function test_staff_cannot_adjust_stock(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($staff)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '10',
                'reason' => 'Stok awal',
            ])
            ->assertForbidden();

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_a_user_cannot_adjust_a_foreign_product(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        [$outsider] = $this->businessWithUser();

        $this->actingAs($outsider)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '10',
                'reason' => 'Stok awal',
            ])
            ->assertForbidden();

        $this->assertSame('0.00', (string) $product->fresh()->stock);
    }

    public function test_the_product_page_offers_the_form_only_to_owners(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($owner)
            ->get(route('products.show', $product))
            ->assertSee('Catat penyesuaian');

        $this->actingAs($staff)
            ->get(route('products.show', $product))
            ->assertDontSee('Catat penyesuaian');
    }

    public function test_the_ledger_stays_consistent_with_the_stock_column(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->actingAs($user)->post(route('products.stock-adjustments.store', $product), [
            'direction' => 'in',
            'quantity' => '100',
            'reason' => 'Stok awal',
        ]);

        $this->actingAs($user)->post(route('products.stock-adjustments.store', $product), [
            'direction' => 'out',
            'quantity' => '25',
            'reason' => 'Koreksi hitung stok',
        ]);

        $this->assertSame('75.00', (string) $product->fresh()->stock);
        $this->assertSame(
            '75.00',
            number_format((float) $product->movements()->sum('quantity'), 2, '.', ''),
        );
    }
}
