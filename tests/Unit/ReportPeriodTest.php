<?php

namespace Tests\Unit;

use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Rentang tanggal laporan (D-029).
 *
 * Bagian yang paling mudah salah adalah default dan nilai yang tidak
 * terbaca: kalau defaultnya bergeser, seluruh angka dashboard ikut
 * bergeser tanpa ada yang gagal.
 */
class ReportPeriodTest extends TestCase
{
    public function test_it_defaults_to_the_current_month_up_to_today(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/reports/sales'));

        $this->assertSame(now()->startOfMonth()->toDateString(), $period->fromDate());
        $this->assertSame(now()->toDateString(), $period->toDate());
    }

    public function test_it_reads_an_explicit_inclusive_range(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/reports/sales', 'GET', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]));

        $this->assertSame('2026-10-01', $period->fromDate());
        $this->assertSame('2026-10-18', $period->toDate());
        $this->assertSame(['2026-10-01', '2026-10-18'], $period->dates());
        $this->assertSame('01/10/2026 - 18/10/2026', $period->label());
        $this->assertSame('20261001-20261018', $period->slug());
        $this->assertSame(18, $period->dayCount());
    }

    public function test_a_single_day_range_covers_one_day(): void
    {
        $day = CarbonImmutable::parse('2026-10-05');

        $period = new ReportPeriod($day, $day);

        $this->assertSame(1, $period->dayCount());
        $this->assertSame(['2026-10-05', '2026-10-05'], $period->dates());
    }

    public function test_a_missing_side_of_the_range_uses_the_default(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/reports/sales', 'GET', [
            'from' => '2026-01-01',
        ]));

        $this->assertSame('2026-01-01', $period->fromDate());
        $this->assertSame(now()->toDateString(), $period->toDate());
    }

    public function test_an_unparsable_value_falls_back_to_the_default(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/reports/sales', 'GET', [
            'from' => 'salah',
            'to' => '2026-13-45',
        ]));

        $this->assertSame(now()->startOfMonth()->toDateString(), $period->fromDate());
        $this->assertSame(now()->toDateString(), $period->toDate());
    }

    public function test_an_empty_string_is_treated_as_missing(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/reports/sales', 'GET', [
            'from' => '',
            'to' => '  ',
        ]));

        $this->assertSame(now()->startOfMonth()->toDateString(), $period->fromDate());
        $this->assertSame(now()->toDateString(), $period->toDate());
    }
}
