<?php

namespace Tests\Feature\Reports;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PaymentService;
use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesReportableDocuments;
use Tests\TestCase;

/**
 * Laporan piutang pelanggan dan utang supplier.
 *
 * Keduanya saldo **berjalan**: tidak dibatasi rentang tanggal, dan
 * pembayaran yang sudah di-void tidak dihitung sebagai pelunasan
 * (D-026).
 */
class ReceivablePayableReportTest extends TestCase
{
    use CreatesBusinessUsers, CreatesReportableDocuments, RefreshDatabase;

    private function october(): ReportPeriod
    {
        return new ReportPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-18'),
        );
    }

    public function test_receivables_are_grouped_per_customer_and_only_unpaid_ones_appear(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $belumLunas = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Belum Lunas']);
        $belumBayar = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Belum Bayar']);
        $lunas = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Sudah Lunas']);

        $saleA = $this->completedSale($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '100.000', 'unit_cost' => '60.000'],
        ], $belumLunas);

        $this->completedSale($business, '2026-10-06', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '50.000', 'unit_cost' => '60.000'],
        ], $belumBayar);

        $saleC = $this->completedSale($business, '2026-10-07', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '30.000', 'unit_cost' => '60.000'],
        ], $lunas);

        Payment::factory()->forSale($saleA)->create(['amount' => '40000.00', 'paid_at' => '2026-10-08']);
        Payment::factory()->forSale($saleC)->create(['amount' => '30000.00', 'paid_at' => '2026-10-08']);

        $rows = app(ReportingService::class)->receivables($business);

        $this->assertCount(2, $rows);

        $byName = $rows->keyBy('name');

        $this->assertSame(100000.0, (float) $byName['Toko Belum Lunas']->document_total);
        $this->assertSame(40000.0, (float) $byName['Toko Belum Lunas']->paid_total);
        $this->assertSame(60000.0, (float) $byName['Toko Belum Lunas']->due_total);

        $this->assertSame(50000.0, (float) $byName['Toko Belum Bayar']->due_total);
        $this->assertArrayNotHasKey('Toko Sudah Lunas', $byName->all());

        $this->assertSame('110000.00', app(ReportingService::class)->receivableTotal($business));

        $this->actingAs($user)
            ->get(route('reports.receivables'))
            ->assertOk()
            ->assertSee('Toko Belum Lunas')
            ->assertSee('Toko Belum Bayar')
            ->assertDontSee('Toko Sudah Lunas')
            ->assertSee('Rp110.000');
    }

    public function test_a_voided_payment_does_not_count_as_settlement(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();
        $customer = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Transfer Gagal']);

        $sale = $this->completedSale($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '100.000', 'unit_cost' => '60.000'],
        ], $customer);

        Payment::factory()->forSale($sale)->voided($user, 'Transfer gagal di bank')->create([
            'amount' => '100000.00',
            'paid_at' => '2026-10-06',
        ]);

        $rows = app(ReportingService::class)->receivables($business);

        $this->assertCount(1, $rows);
        $this->assertSame(0.0, (float) $rows->first()->paid_total);
        $this->assertSame(100000.0, (float) $rows->first()->due_total);
        $this->assertSame('100000.00', app(ReportingService::class)->receivableTotal($business));
    }

    public function test_a_cancelled_sale_does_not_create_a_receivable(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();
        $customer = Customer::factory()->forBusiness($business)->create();

        $sale = $this->completedSale($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '100.000', 'unit_cost' => '60.000'],
        ], $customer);

        $sale->update(['status' => SaleStatus::Cancelled, 'cancelled_at' => now()]);

        $this->assertSame('0.00', app(ReportingService::class)->receivableTotal($business));
        $this->assertCount(0, app(ReportingService::class)->receivables($business));
    }

    public function test_payables_are_grouped_per_supplier_from_received_purchases(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();
        $supplier = Supplier::factory()->forBusiness($business)->create(['name' => 'PT Utang Jaya']);

        $purchase = $this->receivedPurchase($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '20.000'],
        ], $supplier);

        Payment::factory()->forPurchase($purchase)->create(['amount' => '50000.00', 'paid_at' => '2026-10-06']);

        // Draf belum menimbulkan utang.
        Purchase::factory()->forBusiness($business)->create([
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => '2026-10-07',
            'status' => PurchaseStatus::Draft,
            'subtotal' => '999000.00',
            'total' => '999000.00',
        ]);

        $rows = app(ReportingService::class)->payables($business);

        $this->assertCount(1, $rows);
        $this->assertSame('PT Utang Jaya', $rows->first()->name);
        $this->assertSame(1, (int) $rows->first()->purchase_count);
        $this->assertSame(200000.0, (float) $rows->first()->document_total);
        $this->assertSame(50000.0, (float) $rows->first()->paid_total);
        $this->assertSame(150000.0, (float) $rows->first()->due_total);

        $this->assertSame('150000.00', app(ReportingService::class)->payableTotal($business));

        $this->actingAs($user)
            ->get(route('reports.payables'))
            ->assertOk()
            ->assertSee('PT Utang Jaya')
            ->assertSee('Rp150.000')
            ->assertDontSee('Rp999.000');
    }

    public function test_a_voided_payment_restores_a_supplier_debt(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();
        $supplier = Supplier::factory()->forBusiness($business)->create();

        $purchase = $this->receivedPurchase($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '20.000'],
        ], $supplier);

        $payment = Payment::factory()->forPurchase($purchase)->create([
            'amount' => '200000.00',
            'paid_at' => '2026-10-06',
        ]);

        $this->assertSame('0.00', app(ReportingService::class)->payableTotal($business));

        // Void lewat service yang sama dengan UI (D-026).
        app(PaymentService::class)->void($payment, $user, 'Double input');

        $this->assertSame('200000.00', app(ReportingService::class)->payableTotal($business));
    }

    public function test_receivables_and_payables_ignore_other_businesses(): void
    {
        [$user, $business] = $this->businessWithUser();

        [, $otherBusiness] = $this->businessWithUser();
        $otherProduct = Product::factory()->forBusiness($otherBusiness)->create();
        $otherCustomer = Customer::factory()->forBusiness($otherBusiness)->create();

        $this->completedSale($otherBusiness, '2026-10-05', [
            ['product' => $otherProduct, 'quantity' => 1, 'unit_price' => '777.000', 'unit_cost' => '1.000'],
        ], $otherCustomer);

        $service = app(ReportingService::class);

        $this->assertSame('0.00', $service->receivableTotal($business));
        $this->assertSame('0.00', $service->payableTotal($business));
    }
}
