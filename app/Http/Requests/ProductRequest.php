<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Otorisasi ditangani ProductPolicy di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Harga diisi dengan konvensi Indonesia ("85.000"), jadi dinormalkan
     * menjadi decimal string sebelum aturan `numeric` dijalankan.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sell_price' => Money::parseToDecimal($this->input('sell_price')),
            'cost_price' => Money::parseToDecimal($this->input('cost_price')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $businessId = app(CurrentBusiness::class)->id();
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->getKey() : null;

        return [
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('business_id', $businessId))
                    ->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'sell_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'SKU wajib diisi.',
            'sku.unique' => 'SKU sudah dipakai pada bisnis ini.',
            'sku.max' => 'SKU maksimal 50 karakter.',
            'name.required' => 'Nama produk wajib diisi.',
            'unit.required' => 'Satuan wajib diisi.',
            'sell_price.required' => 'Harga jual wajib diisi.',
            'sell_price.numeric' => 'Harga jual harus berupa angka.',
            'sell_price.min' => 'Harga jual tidak boleh negatif.',
            'cost_price.required' => 'Harga pokok wajib diisi.',
            'cost_price.numeric' => 'Harga pokok harus berupa angka.',
            'cost_price.min' => 'Harga pokok tidak boleh negatif.',
        ];
    }
}
