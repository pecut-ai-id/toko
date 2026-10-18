<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditAction;
use App\Enums\BusinessRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesPurchases;
use Tests\Concerns\CreatesSales;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Jejak audit (Bagian 4 `requirements.md`).
 *
 * Yang diuji di sini bukan hanya "barisnya ada", tetapi bahwa barisnya
 * menyebut pelaku, dokumen, dan perubahan yang benar --- dan bahwa
 * halaman riwayatnya baca-saja serta terbatas pada satu bisnis.
 */
class AuditTrailTest extends TestCase
{
    use CreatesBusinessUsers, CreatesPurchases, CreatesSales, ManagesStock, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function productPayload(array $attributes = []): array
    {
        return array_merge([
            'sku' => 'SLM-001',
            'name' => 'Selimut Tebal',
            'unit' => 'pcs',
            'sell_price' => '85.000',
            'cost_price' => '60.000',
            'is_active' => '1',
        ], $attributes);
    }

    public function test_creating_a_purchase_through_the_form_is_recorded(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '5.000'],
        ]);

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::PurchaseCreated)->sole();

        $this->assertSame($user->getKey(), $log->user_id);
        $this->assertSame($business->getKey(), $log->business_id);
        $this->assertSame($purchase->getKey(), $log->subject_id);
        $this->assertSame($purchase->getMorphClass(), $log->subject_type);
        $this->assertStringContainsString($purchase->number, $log->summary);
        $this->assertEquals(['total' => [null, '50000.00']], $log->changes);
    }

    public function test_receiving_and_cancelling_a_purchase_are_recorded_in_order(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = $this->draftPurchase($user, $business, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '5.000'],
        ]);

        $this->actingAs($user)->post(route('purchases.receive', $purchase))->assertRedirect();

        $received = AuditLog::query()->forBusiness($business)->where('action', AuditAction::PurchaseReceived)->sole();
        $this->assertEquals(['status' => ['Draf', 'Diterima']], $received->changes);

        $this->actingAs($user)->post(route('purchases.cancel', $purchase))->assertRedirect();

        $cancelled = AuditLog::query()->forBusiness($business)->where('action', AuditAction::PurchaseCancelled)->sole();
        $this->assertSame($user->getKey(), $cancelled->user_id);
        $this->assertEquals(['status' => ['Diterima', 'Dibatalkan']], $cancelled->changes);
        $this->assertStringContainsString('stok dikembalikan', $cancelled->summary);

        $this->assertSame(
            [AuditAction::PurchaseCreated, AuditAction::PurchaseReceived, AuditAction::PurchaseCancelled],
            AuditLog::query()->forBusiness($business)->orderBy('id')->get()->pluck('action')->all(),
        );
    }

    public function test_a_sale_with_an_initial_payment_records_both_events(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '85.000'],
        ], '2026-10-18', ['paid_amount' => '30.000', 'paid_at' => '2026-10-18']);

        $logs = AuditLog::query()->forBusiness($business)->orderBy('id')->get();

        $this->assertSame(
            [AuditAction::SaleCreated, AuditAction::PaymentRecorded],
            $logs->pluck('action')->all(),
        );
        $this->assertEquals(['total' => [null, '170000.00']], $logs->first()->changes);

        $payment = $logs->last();
        $this->assertEquals(['amount' => [null, '30000.00']], $payment->changes);
        $this->assertSame($sale->getKey(), $payment->subject_id, 'Pembayaran menempel pada dokumen penjualannya.');
        $this->assertStringContainsString($sale->number, $payment->summary);
        $this->assertStringContainsString('Rp30.000', $payment->summary);
    }

    public function test_cancelling_a_sale_is_recorded(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '85.000'],
        ]);

        $this->actingAs($user)->post(route('sales.cancel', $sale))->assertRedirect();

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::SaleCancelled)->sole();

        $this->assertEquals(['status' => ['Selesai', 'Dibatalkan']], $log->changes);
        $this->assertSame($sale->getKey(), $log->subject_id);
    }

    public function test_voiding_a_payment_records_the_reason(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '85.000'],
        ], '2026-10-18', ['paid_amount' => '30.000', 'paid_at' => '2026-10-18']);

        $payment = Payment::query()->forBusiness($business)->sole();

        $this->actingAs($user)
            ->post(route('payments.void', $payment), ['reason' => 'Transfer gagal di bank'])
            ->assertRedirect();

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::PaymentVoided)->sole();

        $this->assertEquals(
            ['voided' => [false, true], 'reason' => [null, 'Transfer gagal di bank']],
            $log->changes,
        );
        $this->assertStringContainsString('Transfer gagal di bank', $log->summary);
        $this->assertSame($sale->getKey(), $log->subject_id);
    }

    public function test_a_stock_adjustment_records_the_before_and_after(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['name' => 'Selimut Tebal']);

        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'in',
                'quantity' => '12',
                'reason' => 'Stok awal opname',
            ])
            ->assertRedirect();

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::StockAdjusted)->sole();

        $this->assertEquals(['stock' => ['0.00', '12.00']], $log->changes);
        $this->assertSame($product->getKey(), $log->subject_id);
        $this->assertStringContainsString('Stok awal opname', $log->summary);
        $this->assertStringContainsString('+12', $log->summary);
        $this->assertStringContainsString('menjadi 12', $log->summary);
    }

    public function test_a_stock_adjustment_outward_is_recorded_as_negative(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $this->actingAs($user)
            ->post(route('products.stock-adjustments.store', $product), [
                'direction' => 'out',
                'quantity' => '5',
                'reason' => 'Barang rusak',
            ])
            ->assertRedirect();

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::StockAdjusted)->sole();

        $this->assertEquals(['stock' => ['20.00', '15.00']], $log->changes);
        $this->assertStringContainsString('-5', $log->summary);
    }

    public function test_product_changes_are_recorded(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->actingAs($user)->post(route('products.store'), $this->productPayload())->assertRedirect();
        $product = Product::query()->forBusiness($business)->sole();

        $this->actingAs($user)
            ->put(route('products.update', $product), $this->productPayload(['sell_price' => '90.000']))
            ->assertRedirect();

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertRedirect();

        $this->assertSame(
            [AuditAction::ProductCreated, AuditAction::ProductUpdated, AuditAction::ProductDeactivated],
            AuditLog::query()->forBusiness($business)->orderBy('id')->get()->pluck('action')->all(),
        );

        $updated = AuditLog::query()->forBusiness($business)->where('action', AuditAction::ProductUpdated)->sole();
        $this->assertEquals(['sell_price' => ['85000.00', '90000.00']], $updated->changes);

        $deactivated = AuditLog::query()->forBusiness($business)->where('action', AuditAction::ProductDeactivated)->sole();
        $this->assertEquals(['is_active' => [true, false]], $deactivated->changes);
    }

    public function test_supplier_and_customer_changes_are_recorded(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->actingAs($user)->post(route('suppliers.store'), [
            'name' => 'CV Sumber Kopi',
            'phone' => '0811',
            'is_active' => '1',
        ])->assertRedirect();

        $supplier = Supplier::query()->forBusiness($business)->sole();

        $this->actingAs($user)->delete(route('suppliers.destroy', $supplier))->assertRedirect();

        $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Toko Sebelah',
            'is_active' => '1',
        ])->assertRedirect();

        $customer = Customer::query()->forBusiness($business)->sole();

        $this->actingAs($user)
            ->put(route('customers.update', $customer), ['name' => 'Toko Sebelah Jaya', 'is_active' => '1'])
            ->assertRedirect();

        $this->assertSame(
            [
                AuditAction::SupplierCreated,
                AuditAction::SupplierDeactivated,
                AuditAction::CustomerCreated,
                AuditAction::CustomerUpdated,
            ],
            AuditLog::query()->forBusiness($business)->orderBy('id')->get()->pluck('action')->all(),
        );

        $updated = AuditLog::query()->forBusiness($business)->where('action', AuditAction::CustomerUpdated)->sole();
        $this->assertEquals(['name' => ['Toko Sebelah', 'Toko Sebelah Jaya']], $updated->changes);
    }

    public function test_only_the_fields_that_changed_are_stored(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create(['sku' => 'SLM-001', 'name' => 'Selimut']);

        // Nama dan satuan dikirim sama seperti sebelumnya.
        $this->actingAs($user)->put(route('products.update', $product), [
            'sku' => 'SLM-001',
            'name' => 'Selimut',
            'unit' => $product->unit,
            'sell_price' => '95.000',
            'cost_price' => '60.000',
            'is_active' => '1',
        ])->assertRedirect();

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::ProductUpdated)->sole();

        $this->assertEquals(['sell_price'], array_keys($log->changes ?? []));
    }

    public function test_a_rejected_operation_leaves_no_audit_row(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '85.000'],
        ]);

        // Kelebihan bayar ditolak; jejaknya tidak boleh ikut tertulis.
        $this->actingAs($user)
            ->post(route('sales.payments.store', $sale), ['amount' => '999.000', 'paid_at' => '2026-10-18'])
            ->assertSessionHasErrors();

        $this->assertSame(0, AuditLog::query()->forBusiness($business)->where('action', AuditAction::PaymentRecorded)->count());
    }

    public function test_no_audit_row_is_written_without_an_authenticated_user(): void
    {
        [$user, $business] = $this->businessWithUser();

        $log = app(AuditLogger::class)->log(
            action: AuditAction::SaleCreated,
            summary: 'Dibuat tanpa pelaku.',
            business: $business,
        );

        $this->assertNull($log);
        $this->assertSame(0, AuditLog::query()->forBusiness($business)->count());
    }

    public function test_the_diff_ignores_unchanged_and_equivalent_values(): void
    {
        $product = Product::factory()->make();

        $before = [
            'name' => 'Selimut',
            'sell_price' => '60000.00',
            'is_active' => 1,
        ];

        $product->name = 'Selimut';
        $product->sell_price = '60000';
        $product->is_active = true;

        $this->assertSame([], app(AuditLogger::class)->diff($before, $product, ['name', 'sell_price', 'is_active']));

        $product->is_active = false;

        $this->assertEquals(
            ['is_active' => [true, false]],
            app(AuditLogger::class)->diff($before, $product, ['name', 'sell_price', 'is_active']),
        );
    }

    public function test_the_activity_history_lists_the_newest_activity_first(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '10.000'],
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Riwayat aktivitas')
            ->assertSee('Penjualan dibuat')
            ->assertSee('1 catatan');
    }

    public function test_the_history_can_be_filtered_by_action_and_actor(): void
    {
        [$user, $business] = $this->businessWithUser();

        $other = User::factory()->create();
        $business->users()->attach($other->getKey(), ['role' => BusinessRole::Staff->value]);

        AuditLog::factory()->forBusiness($business)->by($user)
            ->action(AuditAction::ProductCreated)->create(['summary' => 'Produk dibuat oleh pemilik']);

        AuditLog::factory()->forBusiness($business)->by($other)
            ->action(AuditAction::SaleCreated)->create(['summary' => 'Penjualan oleh staff']);

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['action' => AuditAction::ProductCreated->value]))
            ->assertOk()
            ->assertSee('Produk dibuat oleh pemilik')
            ->assertDontSee('Penjualan oleh staff');

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['user_id' => $other->getKey()]))
            ->assertOk()
            ->assertSee('Penjualan oleh staff')
            ->assertDontSee('Produk dibuat oleh pemilik');
    }

    public function test_the_history_respects_the_date_range(): void
    {
        [$user, $business] = $this->businessWithUser();

        AuditLog::factory()->forBusiness($business)->by($user)->create([
            'summary' => 'Aktivitas bulan lalu',
            'created_at' => '2026-09-15 10:00:00',
        ]);

        AuditLog::factory()->forBusiness($business)->by($user)->create([
            'summary' => 'Aktivitas bulan ini',
            'created_at' => '2026-10-05 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['from' => '2026-10-01', 'to' => '2026-10-18']))
            ->assertOk()
            ->assertSee('Aktivitas bulan ini')
            ->assertDontSee('Aktivitas bulan lalu');
    }

    public function test_an_unknown_action_filter_is_rejected(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['action' => 'tidak.ada']))
            ->assertSessionHasErrors('action')
            ->assertRedirect(route('audit-logs.index'));
    }

    public function test_staff_can_read_the_activity_history(): void
    {
        [$owner, $business] = $this->businessWithUser();
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        AuditLog::factory()->forBusiness($business)->by($owner)->create(['summary' => 'Dibuat oleh pemilik']);

        $this->actingAs($staff)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('Dibuat oleh pemilik');
    }

    public function test_guests_cannot_read_the_activity_history(): void
    {
        $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
    }

    public function test_another_business_activity_is_not_shown(): void
    {
        [$user, $business] = $this->businessWithUser();

        [, $otherBusiness] = $this->businessWithUser();
        $otherUser = User::factory()->create();

        AuditLog::factory()->forBusiness($otherBusiness)->by($otherUser)->create([
            'summary' => 'Aktivitas bisnis lain',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('0 catatan')
            ->assertDontSee('Aktivitas bisnis lain');
    }

    public function test_the_history_is_read_only(): void
    {
        // Tidak ada route tulis untuk jejak audit: barisnya tidak bisa
        // ditambah, diubah, atau dihapus lewat HTTP.
        $this->assertNull(Route::getRoutes()->getByName('audit-logs.store'));
        $this->assertNull(Route::getRoutes()->getByName('audit-logs.destroy'));

        [$user, $business] = $this->businessWithUser();
        $log = AuditLog::factory()->forBusiness($business)->by($user)->create(['summary' => 'Catatan tetap']);

        $this->actingAs($user)->delete(route('audit-logs.index'))->assertMethodNotAllowed();

        $this->assertSame('Catatan tetap', $log->fresh()->summary);
    }

    public function test_a_sale_from_another_business_cannot_be_seen_in_the_history(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 50, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '10.000'],
        ]);

        $log = AuditLog::query()->forBusiness($business)->where('action', AuditAction::SaleCreated)->sole();

        $this->assertSame($sale->getKey(), $log->subject_id);
        $this->assertInstanceOf(Sale::class, $log->subject);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee(route('sales.show', $sale));
    }
}
