<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'sku' => strtoupper(Str::random(8)),
            'name' => Str::title(fake()->words(3, true)),
            'unit' => 'pcs',
            'sell_price' => 85000,
            'cost_price' => 60000,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => ['business_id' => $business->getKey()]);
    }
}
