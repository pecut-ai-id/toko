<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function parseCases(): array
    {
        return [
            'ribuan dengan titik' => ['85.000', '85000.00'],
            'ribuan panjang' => ['1.275.000', '1275000.00'],
            'rupiah dan koma desimal' => ['Rp 85.000,50', '85000.50'],
            'koma desimal saja' => ['85,5', '85.50'],
            'titik dengan satu digit desimal' => ['85.5', '85.50'],
            'titik dengan dua digit desimal' => ['1.234,56', '1234.56'],
            'angka polos' => ['85000', '85000.00'],
            'negatif' => ['-1.000', '-1000.00'],
            'nol' => ['0', '0.00'],
            'teks tidak valid' => ['abc', null],
            'kosong' => ['', null],
            'null' => [null, null],
        ];
    }

    /**
     * @dataProvider parseCases
     */
    #[DataProvider('parseCases')]
    public function test_it_parses_indonesian_money_input(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Money::parseToDecimal($input));
    }

    public function test_it_formats_money_for_indonesian_display(): void
    {
        $this->assertSame('Rp1.275.000', Money::rupiah('1275000.00'));
        $this->assertSame('Rp85.000,50', Money::rupiah('85000.50'));
        $this->assertSame('Rp0', Money::rupiah('0.00'));
        $this->assertSame('-Rp1.000', Money::rupiah('-1000'));
        $this->assertSame('1.000', Money::format('1000'));
    }

    public function test_it_multiplies_and_sums_with_explicit_rounding(): void
    {
        $this->assertSame('500000.00', Money::multiply('50000.00', '10'));
        $this->assertSame('2550001.00', Money::multiply('1275000.50', '2'));
        $this->assertSame('3050001.00', Money::sum(['500000.00', '2550001.00']));
    }

    public function test_rupiah_helper_is_available_globally(): void
    {
        $this->assertSame('Rp1.500', rupiah(1500));
        $this->assertSame('1.500,25', angka('1500.25'));
    }
}
