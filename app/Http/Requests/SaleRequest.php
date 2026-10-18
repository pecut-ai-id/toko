<?php

namespace App\Http\Requests;

use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    /**
     * Otorisasi ditangani SalePolicy di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Baris kosong dari UI dibuang, dan semua nilai uang diubah dari
     * format Indonesia menjadi decimal string sebelum divalidasi.
     *
     * Total tidak pernah diambil dari browser; server menghitung ulang
     * dari kuantitas dan harga satuan.
     */
    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($item): bool => is_array($item) && filled($item['product_id'] ?? null))
            ->map(fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'quantity' => Money::parseToDecimal($item['quantity'] ?? null),
                'unit_price' => Money::parseToDecimal($item['unit_price'] ?? null),
            ])
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
            'paid_amount' => filled($this->input('paid_amount'))
                ? Money::parseToDecimal($this->input('paid_amount'))
                : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $businessId = app(CurrentBusiness::class)->id();

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('business_id', $businessId),
            ],
            'sold_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('business_id', $businessId),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'customer_id.exists' => 'Pelanggan tidak ditemukan pada bisnis ini.',
            'sold_at.required' => 'Tanggal penjualan wajib diisi.',
            'sold_at.date' => 'Tanggal penjualan tidak valid.',
            'items.required' => 'Penjualan harus memiliki minimal satu item.',
            'items.min' => 'Penjualan harus memiliki minimal satu item.',
            'items.*.product_id.required' => 'Produk pada setiap item wajib dipilih.',
            'items.*.product_id.exists' => 'Produk tidak ditemukan pada bisnis ini.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.quantity.gt' => 'Kuantitas harus lebih dari nol.',
            'items.*.unit_price.required' => 'Harga jual wajib diisi.',
            'items.*.unit_price.min' => 'Harga jual tidak boleh negatif.',
            'paid_amount.numeric' => 'Jumlah dibayar harus berupa angka.',
            'paid_amount.min' => 'Jumlah dibayar tidak boleh negatif.',
        ];
    }
}
