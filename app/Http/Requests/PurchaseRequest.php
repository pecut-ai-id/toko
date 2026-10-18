<?php

namespace App\Http\Requests;

use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    /**
     * Otorisasi ditangani PurchasePolicy di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Baris kosong dari UI dibuang, dan nilai uang diubah dari format
     * Indonesia ("1.275.000") menjadi decimal string sebelum divalidasi.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item): bool => is_array($item) && filled($item['product_id'] ?? null))
            ->map(fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'quantity' => Money::parseToDecimal($item['quantity'] ?? null),
                'unit_cost' => Money::parseToDecimal($item['unit_cost'] ?? null),
            ])
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $businessId = app(CurrentBusiness::class)->id();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where('business_id', $businessId),
            ],
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier tidak ditemukan pada bisnis ini.',
            'purchased_at.required' => 'Tanggal pembelian wajib diisi.',
            'purchased_at.date' => 'Tanggal pembelian tidak valid.',
            'items.required' => 'Pembelian harus memiliki minimal satu item.',
            'items.min' => 'Pembelian harus memiliki minimal satu item.',
            'items.*.product_id.required' => 'Produk pada setiap item wajib dipilih.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan pada bisnis ini.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.quantity.gt' => 'Kuantitas harus lebih dari nol.',
            'items.*.unit_cost.required' => 'Harga beli wajib diisi.',
            'items.*.unit_cost.min' => 'Harga beli tidak boleh negatif.',
        ];
    }
}
